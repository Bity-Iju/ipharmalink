<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;

/**
 * Platform-wide reporting.
 *
 * Every report is scoped to a date range, aggregated in SQL (never by loading
 * rows into PHP) and exportable as CSV.
 */
final class ReportAdminController extends Controller
{
    private const PER_PAGE = 20;

    /** Report slug => human title, used by the index and the export handler. */
    private const REPORTS = [
        'sales'       => 'Sales & revenue',
        'orders'      => 'Orders',
        'pharmacies'  => 'Pharmacies',
        'customers'   => 'Customers',
        'products'    => 'Products',
        'commissions' => 'Commissions',
        'payments'    => 'Payments',
        'deliveries'  => 'Deliveries',
        'refunds'     => 'Refunds',
        'inventory'   => 'Inventory',
    ];

    // -----------------------------------------------------------------------
    //  GET /admin/reports
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        [$from, $to] = $this->range($request);
        $db          = Database::instance();

        $window = ['from' => $from, 'to' => $to];

        $tiles = [
            'gmv'            => (float) $db->value(
                "SELECT COALESCE(SUM(total), 0) FROM orders
                 WHERE DATE(created_at) BETWEEN :from AND :to AND status NOT IN ('cancelled','refunded')",
                $window
            ),            'orders'         => (int) $db->value(
                'SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN :from AND :to',
                $window
            ),
            // No date filter on this one — it is a lifetime figure.
            'commission'     => (float) $db->value(
                "SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE status <> 'reversed'"
            ),
            'refunded'       => (float) $db->value(
                "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'completed' AND DATE(created_at) BETWEEN :from AND :to",
                $window
            ),
            'pharmacies'     => (int) $db->value("SELECT COUNT(*) FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL"),
            'customers'      => (int) $db->value(
                "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'customer' AND u.deleted_at IS NULL"
            ),
            'delivered'      => (int) $db->value(
                "SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(created_at) BETWEEN :from AND :to",
                $window
            ),
            'failed_payments' => (int) $db->value(
                "SELECT COUNT(*) FROM payments WHERE status = 'failed' AND DATE(created_at) BETWEEN :from AND :to",
                $window
            ),
        ];

        // Daily series for the chart.
        $series = $db->all(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS revenue
             FROM orders
             WHERE DATE(created_at) BETWEEN :from AND :to
               AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            $window
        );

        $topPharmacies = $db->all(
            "SELECT ph.name, ph.city, ph.state,
                    COUNT(DISTINCT po.id) AS orders,
                    COALESCE(SUM(po.pharmacy_earnings), 0) AS earnings
             FROM pharmacy_orders po
             JOIN pharmacies ph ON ph.id = po.pharmacy_id
             WHERE DATE(po.created_at) BETWEEN :from AND :to AND po.status NOT IN ('cancelled','refunded')
             GROUP BY ph.id, ph.name, ph.city, ph.state
             ORDER BY earnings DESC LIMIT 10",
            $window
        );

        $this->view('admin/reports/index', [
            'title'          => 'Reports',
            'tiles'          => $tiles,
            'series'         => $series,
            'topPharmacies'  => $topPharmacies,
            'reports'        => self::REPORTS,
            'from'           => $from,
            'to'             => $to,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/reports/{report}
    // -----------------------------------------------------------------------
    public function sales(Request $request): void
    {
        $this->report($request, 'sales', 'Sales & revenue', $this->salesQuery());
    }

    public function orders(Request $request): void
    {
        $this->report($request, 'orders', 'Orders', $this->ordersQuery());
    }

    public function pharmacies(Request $request): void
    {
        $this->report($request, 'pharmacies', 'Pharmacies', $this->pharmaciesQuery());
    }

    public function customers(Request $request): void
    {
        $this->report($request, 'customers', 'Customers', $this->customersQuery());
    }

    public function products(Request $request): void
    {
        $this->report($request, 'products', 'Products', $this->productsQuery());
    }

    public function commissions(Request $request): void
    {
        $this->report($request, 'commissions', 'Commissions', $this->commissionsQuery());
    }

    public function payments(Request $request): void
    {
        $this->report($request, 'payments', 'Payments', $this->paymentsQuery());
    }

    public function deliveries(Request $request): void
    {
        $this->report($request, 'deliveries', 'Deliveries', $this->deliveriesQuery());
    }

    public function refunds(Request $request): void
    {
        $this->report($request, 'refunds', 'Refunds', $this->refundsQuery());
    }

    public function inventory(Request $request): void
    {
        $this->report($request, 'inventory', 'Inventory', $this->inventoryQuery());
    }

    // -----------------------------------------------------------------------
    //  GET /admin/reports/export/{report}
    // -----------------------------------------------------------------------
    public function export(Request $request, array $params): void
    {
        $slug  = $this->param('report', $params);
        $spec  = $this->spec($slug);
        [$from, $to] = $this->range($request);

        $method = lcfirst($spec['method']);
        $query  = $this->{$method}();
        $params = $this->withRange($query['params'], $query['sql'], $from, $to);
        $rows   = Database::instance()->all($query['sql'], $params);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $spec['columns']);

        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($spec['columns']) as $column) {
                $value = $row[$column] ?? '';
                $line[] = is_scalar($value) ? (string) $value : '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $filename = sprintf('ipharmalink-%s-%s-to-%s.csv', $slug, $from, $to);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    /**
     * Render one report: summary tiles plus a paged table.
     *
     * @param array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} $query
     */
    private function report(Request $request, string $slug, string $heading, array $query): void
    {
        [$from, $to]              = $this->range($request);
        $params                   = $query['params'];
        $params                   = $this->withRange($params, $query['sql'], $from, $to);
        $perPage                  = $this->perPage(self::PER_PAGE);
        $page                     = $this->page();
        $countSql                 = $this->countable($query['sql']);

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value($countSql, $params),
            static function (Database $d, int $limit, int $offset) use ($query, $params, $perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    $query['sql'] . " LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $rows  = $paginator->items();
        $tiles = [];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (is_numeric($value)) {
                    $tiles[$key] = ($tiles[$key] ?? 0) + (float) $value;
                }
            }
        }

        $this->view('admin/reports/report', [
            'title'     => $heading,
            'slug'      => $slug,
            'paginator' => $paginator,
            'columns'   => $query['columns'],
            'tiles'     => $tiles,
            'from'      => $from,
            'to'        => $to,
            'reports'   => self::REPORTS,
        ], 'layouts/dashboard');
    }

    /**
     * Public wrapper around countable() for tooling and tests.
     */
    public function countFor(string $sql): string
    {
        return $this->countable($sql);
    }

    /**
     * Public wrapper around withRange() for tooling and tests.
     *
     * @param  array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function bindRange(array $params, string $sql, string $from, string $to): array
    {
        return $this->withRange($params, $sql, $from, $to);
    }

    /**
     * Turn a SELECT into a COUNT by wrapping it as a derived table.
     *
     * Stripping the projection with a regex is unreliable once queries have
     * GROUP BY, computed aliases or DISTINCT, so the projection is left
     * intact and only the trailing ORDER BY is removed.
     */
    private function countable(string $sql): string
    {
        $sql = trim($sql);

        // Only strip an ORDER BY that is the statement's final clause. A naive
        // match would also hit an ORDER BY nested inside GROUP_CONCAT and
        // truncate the query mid-expression.
        $lastParen = strrpos($sql, ')');
        $orderAt   = strripos($sql, ' ORDER BY ');
        if ($orderAt !== false && ($lastParen === false || $orderAt > $lastParen)) {
            $sql = rtrim(substr($sql, 0, $orderAt));
        }

        return 'SELECT COUNT(*) FROM (' . rtrim($sql, "; \t\n\r") . ') AS countable';
    }

    /**
     * Bind the date window, but only to statements that reference it.
     * Passing params a query does not use raises a PDO error.
     *
     * @param  array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function withRange(array $params, string $sql, string $from, string $to): array
    {
        if (str_contains($sql, ':from')) {
            $params['from'] = $from;
        }
        if (str_contains($sql, ':to')) {
            $params['to'] = $to;
        }
        return $params;
    }

    /**
     * Resolve a report slug to its spec, or fail with a 404.
     *
     * @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string}
     */
    private function spec(string $slug): array
    {
        $map = [
            'sales'       => 'salesQuery',
            'orders'      => 'ordersQuery',
            'pharmacies'  => 'pharmaciesQuery',
            'customers'   => 'customersQuery',
            'products'    => 'productsQuery',
            'commissions' => 'commissionsQuery',
            'payments'    => 'paymentsQuery',
            'deliveries'  => 'deliveriesQuery',
            'refunds'     => 'refundsQuery',
            'inventory'   => 'inventoryQuery',
        ];

        if (!isset($map[$slug])) {
            throw new HttpException(404, 'Unknown report.');
        }

        return $this->{$map[$slug]}();
    }

    /**
     * Normalise the requested date window, defaulting to the last 30 days.
     *
     * @return array{0:string,1:string}
     */
    private function range(Request $request): array
    {
        $from = (string) $request->query('from', date('Y-m-d', strtotime('-29 days')));
        $to   = (string) $request->query('to', date('Y-m-d'));

        if (strtotime($from) === false) {
            $from = date('Y-m-d', strtotime('-29 days'));
        }
        if (strtotime($to) === false) {
            $to = date('Y-m-d');
        }

        $from = max($from, '2000-01-01');
        $to   = min($to, date('Y-m-d'));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    // -----------------------------------------------------------------------
    //  Report queries
    // -----------------------------------------------------------------------

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function salesQuery(): array
    {
        return [
            'sql' => "SELECT DATE(o.created_at) AS day,
                             COUNT(DISTINCT o.id) AS orders,
                             COUNT(DISTINCT po.id) AS pharmacy_slices,
                             COALESCE(SUM(po.pharmacy_earnings), 0) AS pharmacy_earnings,
                             COALESCE(SUM(po.platform_fee), 0) AS platform_commission,
                             COALESCE(SUM(po.total), 0) AS gross_merchandise_value
                      FROM pharmacy_orders po
                      JOIN orders o ON o.id = po.order_id
                      WHERE DATE(po.created_at) BETWEEN :from AND :to
                        AND po.status NOT IN ('cancelled','refunded')
                      GROUP BY DATE(po.created_at)
                      ORDER BY day DESC",
            'params'  => [],
            'columns' => [
                'day'                  => 'Day',
                'orders'               => 'Orders',
                'pharmacy_slices'      => 'Pharmacy orders',
                'gross_merchandise_value' => 'GMV',
                'platform_commission'  => 'Commission',
                'pharmacy_earnings'    => 'Pharmacy earnings',
            ],
            'method'  => 'sales',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function ordersQuery(): array
    {
        return [
            'sql' => "SELECT o.order_number,
                             u.full_name AS customer,
                             GROUP_CONCAT(DISTINCT ph.name ORDER BY ph.name SEPARATOR ', ') AS pharmacies,
                             o.fulfilment_method,
                             o.status,
                             o.payment_status,
                             o.total,
                             DATE(o.created_at) AS day
                      FROM orders o
                      JOIN users u ON u.id = o.customer_id
                      LEFT JOIN pharmacy_orders po ON po.order_id = o.id
                      LEFT JOIN pharmacies ph     ON ph.id = po.pharmacy_id
                      WHERE DATE(o.created_at) BETWEEN :from AND :to
                      GROUP BY o.id, o.order_number, u.full_name, o.fulfilment_method,
                               o.status, o.payment_status, o.total, o.created_at
                      ORDER BY o.created_at DESC",
            'params'  => [],
            'columns' => [
                'order_number'   => 'Order',
                'customer'       => 'Customer',
                'pharmacies'     => 'Pharmacies',
                'fulfilment_method' => 'Method',
                'status'         => 'Status',
                'payment_status' => 'Payment',
                'total'          => 'Total',
                'day'            => 'Day',
            ],
            'method'  => 'orders',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function pharmaciesQuery(): array
    {
        return [
            'sql' => "SELECT ph.name,
                             ph.city,
                             ph.state,
                             ph.status,
                             DATE(ph.created_at) AS registered,
                             (SELECT COUNT(*) FROM products p
                               WHERE p.pharmacy_id = ph.id AND p.deleted_at IS NULL) AS products,
                             (SELECT COUNT(*) FROM pharmacy_orders po WHERE po.pharmacy_id = ph.id) AS orders,
                             (SELECT COALESCE(SUM(po.total), 0) FROM pharmacy_orders po
                               WHERE po.pharmacy_id = ph.id AND po.status NOT IN ('cancelled','refunded')) AS gmv,
                             (SELECT COALESCE(SUM(po.pharmacy_earnings), 0) FROM pharmacy_orders po
                               WHERE po.pharmacy_id = ph.id AND po.status = 'delivered') AS earnings
                      FROM pharmacies ph
                      WHERE DATE(ph.created_at) BETWEEN :from AND :to AND ph.deleted_at IS NULL
                      ORDER BY gmv DESC",
            'params'  => [],
            'columns' => [
                'name'       => 'Pharmacy',
                'city'       => 'City',
                'state'      => 'State',
                'status'     => 'Status',
                'registered' => 'Registered',
                'products'   => 'Products',
                'orders'     => 'Orders',
                'gmv'        => 'GMV',
                'earnings'   => 'Earnings',
            ],
            'method'  => 'pharmacies',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function customersQuery(): array
    {
        return [
            'sql' => "SELECT u.full_name,
                             u.email,
                             u.phone,
                             u.status,
                             DATE(u.created_at) AS registered,
                             (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS orders,
                             (SELECT COALESCE(SUM(o.total), 0) FROM orders o
                               WHERE o.customer_id = u.id AND o.status NOT IN ('cancelled','refunded')) AS spent
                      FROM users u
                      JOIN roles r ON r.id = u.role_id
                      WHERE r.name = 'customer' AND u.deleted_at IS NULL
                        AND DATE(u.created_at) BETWEEN :from AND :to
                      ORDER BY spent DESC",
            'params'  => [],
            'columns' => [
                'full_name'  => 'Customer',
                'email'      => 'Email',
                'phone'      => 'Phone',
                'status'     => 'Status',
                'registered' => 'Registered',
                'orders'     => 'Orders',
                'spent'      => 'Spent',
            ],
            'method'  => 'customers',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function productsQuery(): array
    {
        return [
            'sql' => "SELECT p.sku,
                             p.name,
                             ph.name AS pharmacy,
                             p.product_class,
                             p.price,
                             p.stock_qty,
                             p.sales_count,
                             COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue
                      FROM products p
                      JOIN pharmacies ph   ON ph.id = p.pharmacy_id
                      LEFT JOIN order_items oi ON oi.product_id = p.id
                      LEFT JOIN orders o        ON o.id = oi.order_id
                                                AND DATE(o.created_at) BETWEEN :from AND :to
                                                AND o.status NOT IN ('cancelled','refunded')
                      WHERE p.deleted_at IS NULL
                      GROUP BY p.id, p.sku, p.name, ph.name, p.product_class,
                               p.price, p.stock_qty, p.sales_count
                      ORDER BY revenue DESC",
            'params'  => [],
            'columns' => [
                'sku'            => 'SKU',
                'name'           => 'Product',
                'pharmacy'       => 'Pharmacy',
                'product_class'  => 'Class',
                'price'          => 'Price',
                'stock_qty'      => 'Stock',
                'sales_count'    => 'Units sold',
                'revenue'        => 'Revenue',
            ],
            'method'  => 'products',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function commissionsQuery(): array
    {
        return [
            'sql' => "SELECT DATE(c.created_at) AS day,
                             ph.name AS pharmacy,
                             c.rate_percent,
                             c.base_amount,
                             c.commission_amount,
                             c.pharmacy_earnings,
                             c.status
                      FROM commissions c
                      JOIN pharmacies ph ON ph.id = c.pharmacy_id
                      WHERE DATE(c.created_at) BETWEEN :from AND :to
                      ORDER BY c.created_at DESC",
            'params'  => [],
            'columns' => [
                'day'               => 'Day',
                'pharmacy'          => 'Pharmacy',
                'rate_percent'      => 'Rate %',
                'base_amount'       => 'Base',
                'commission_amount' => 'Commission',
                'pharmacy_earnings' => 'Pharmacy earnings',
                'status'            => 'Status',
            ],
            'method'  => 'commissions',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function paymentsQuery(): array
    {
        return [
            'sql' => "SELECT pay.reference,
                             o.order_number,
                             u.email AS customer,
                             pay.gateway_code,
                             pay.amount,
                             pay.status,
                             DATE(pay.created_at) AS day
                      FROM payments pay
                      JOIN orders o ON o.id = pay.order_id
                      JOIN users  u ON u.id = pay.customer_id
                      WHERE DATE(pay.created_at) BETWEEN :from AND :to
                      ORDER BY pay.created_at DESC",
            'params'  => [],
            'columns' => [
                'reference'     => 'Reference',
                'order_number'  => 'Order',
                'customer'      => 'Customer',
                'gateway_code'  => 'Gateway',
                'amount'        => 'Amount',
                'status'        => 'Status',
                'day'           => 'Day',
            ],
            'method'  => 'payments',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function deliveriesQuery(): array
    {
        return [
            'sql' => "SELECT de.tracking_number,
                             ph.name AS pharmacy,
                             u.full_name AS rider,
                             de.status,
                             de.distance_km,
                             de.delivery_fee,
                             DATE(de.created_at) AS day,
                             DATE(de.delivered_at) AS delivered_on
                      FROM deliveries de
                      JOIN pharmacies ph ON ph.id = de.pharmacy_id
                      LEFT JOIN delivery_personnel dp ON dp.id = de.personnel_id
                      LEFT JOIN users u              ON u.id = dp.user_id
                      WHERE DATE(de.created_at) BETWEEN :from AND :to
                      ORDER BY de.created_at DESC",
            'params'  => [],
            'columns' => [
                'tracking_number' => 'Tracking',
                'pharmacy'        => 'Pharmacy',
                'rider'           => 'Rider',
                'status'          => 'Status',
                'distance_km'     => 'Distance (km)',
                'delivery_fee'    => 'Fee',
                'day'             => 'Created',
                'delivered_on'    => 'Delivered',
            ],
            'method'  => 'deliveries',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function refundsQuery(): array
    {
        return [
            'sql' => "SELECT r.id,
                             o.order_number,
                             u.full_name AS customer,
                             r.amount,
                             r.reason,
                             r.status,
                             DATE(r.created_at) AS day
                      FROM refunds r
                      JOIN orders o ON o.id = r.order_id
                      JOIN users  u ON u.id = o.customer_id
                      WHERE DATE(r.created_at) BETWEEN :from AND :to
                      ORDER BY r.created_at DESC",
            'params'  => [],
            'columns' => [
                'id'           => '#',
                'order_number' => 'Order',
                'customer'     => 'Customer',
                'amount'       => 'Amount',
                'reason'       => 'Reason',
                'status'       => 'Status',
                'day'          => 'Day',
            ],
            'method'  => 'refunds',
        ];
    }

    /** @return array{sql:string,params:array<string,mixed>,columns:array<string,string>,method:string} */
    private function inventoryQuery(): array
    {
        return [
            'sql' => "SELECT p.sku,
                             p.name AS product,
                             ph.name AS pharmacy,
                             p.stock_qty,
                             p.min_stock_level,
                             p.batch_number,
                             p.expiry_date,
                             (p.stock_qty * p.price) AS stock_value,
                             CASE WHEN p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE() THEN 'expired'
                                  WHEN p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'expiring'
                                  WHEN p.stock_qty <= p.min_stock_level THEN 'low'
                                  ELSE 'ok' END AS health
                      FROM products p
                      JOIN pharmacies ph ON ph.id = p.pharmacy_id
                      WHERE p.deleted_at IS NULL
                      ORDER BY health DESC, p.stock_qty ASC",
            'params'  => [],
            'columns' => [
                'sku'             => 'SKU',
                'product'         => 'Product',
                'pharmacy'        => 'Pharmacy',
                'stock_qty'       => 'Stock',
                'min_stock_level' => 'Reorder at',
                'batch_number'    => 'Batch',
                'expiry_date'     => 'Expiry',
                'stock_value'     => 'Value',
                'health'          => 'Health',
            ],
            'method'  => 'inventory',
        ];
    }
}
