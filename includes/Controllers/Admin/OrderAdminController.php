<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;

/**
 * Admin order oversight — platform-wide retail orders, their per-pharmacy
 * slices, status timeline and delivery records.
 *
 * The admin view is strictly read-only. Order state transitions belong to the
 * pharmacy, the rider or the customer; an admin can only inspect them here and
 * find them in the audit log.
 */
final class OrderAdminController extends Controller
{
    private const PER_PAGE = 20;

    /** Statuses rolled into each dashboard tab. */
    private const TAB_STATUSES = [
        'pending'    => ['pending_payment', 'paid'],
        'processing' => ['received', 'processing', 'preparing', 'ready_for_pickup', 'ready_for_delivery', 'out_for_delivery'],
        'delivered'  => ['delivered'],
        'cancelled'  => ['cancelled', 'refunded'],
    ];

    // -----------------------------------------------------------------------
    //  GET /admin/orders
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $this->listing($request, '', 'Orders');
    }

    /** GET /admin/orders/pending */
    public function pending(Request $request): void
    {
        $this->listing($request, 'pending', 'Pending orders');
    }

    /** GET /admin/orders/processing */
    public function processing(Request $request): void
    {
        $this->listing($request, 'processing', 'Processing orders');
    }

    /** GET /admin/orders/delivered */
    public function delivered(Request $request): void
    {
        $this->listing($request, 'delivered', 'Delivered orders');
    }

    /** GET /admin/orders/cancelled */
    public function cancelled(Request $request): void
    {
        $this->listing($request, 'cancelled', 'Cancelled & refunded orders');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/orders/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $order = Database::instance()->first(
            'SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
             FROM orders o
             JOIN users u ON u.id = o.customer_id
             WHERE o.id = ?',
            ['id' => $id]
        );

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        $db = Database::instance();

        $order['items'] = $db->all(
            'SELECT oi.*, p.slug AS product_slug
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC',
            ['order_id' => $id]
        );

        // One slice per fulfilling pharmacy — the B2B sub-order structure.
        $order['slices'] = $db->all(
            'SELECT po.*, ph.name AS pharmacy_name, ph.slug AS pharmacy_slug
             FROM pharmacy_orders po
             JOIN pharmacies ph ON ph.id = po.pharmacy_id
             WHERE po.order_id = ?
             ORDER BY po.id ASC',
            ['order_id' => $id]
        );

        $order['history'] = $db->all(
            'SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id ASC',
            ['order_id' => $id]
        );

        $order['deliveries'] = $db->all(
            'SELECT de.*, u.full_name AS personnel_name
             FROM deliveries de
             LEFT JOIN delivery_personnel dp ON dp.id = de.personnel_id
             LEFT JOIN users u              ON u.id = dp.user_id
             WHERE de.order_id = ?
             ORDER BY de.id ASC',
            ['order_id' => $id]
        );

        $order['payments'] = $db->all(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id ASC',
            ['order_id' => $id]
        );

        $this->view('admin/orders/show', [
            'title' => 'Order ' . $order['order_number'],
            'order' => $order,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  Shared listing
    // -----------------------------------------------------------------------

    private function listing(Request $request, string $tab, string $heading): void
    {
        $db      = Database::instance();
        $search  = $request->trimmed('q');
        $status  = $request->trimmed('status');
        $from    = $request->trimmed('from');
        $to      = $request->trimmed('to');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = [];
        $params = [];

        // A tab is a pre-set status group; an explicit status filter wins.
        if ($status !== '') {
            $where[]        = 'o.status = :status';
            $params['status'] = $status;
        } elseif (isset(self::TAB_STATUSES[$tab])) {
            $statuses         = self::TAB_STATUSES[$tab];
            $placeholders     = [];
            foreach ($statuses as $i => $value) {
                $key = "tab{$i}";
                $placeholders[] = ':' . $key;
                $params[$key]   = $value;
            }
            $where[] = 'o.status IN (' . implode(',', $placeholders) . ')';
        }

        if ($search !== '') {
            $where[]  = '(o.order_number LIKE :q OR u.full_name LIKE :q2 OR u.email LIKE :q3 OR u.phone LIKE :q4)';
            $params  += [
                'q'  => '%' . $search . '%',
                'q2' => '%' . $search . '%',
                'q3' => '%' . $search . '%',
                'q4' => '%' . $search . '%',
            ];
        }

        if ($from !== '' && strtotime($from) !== false) {
            $where[]         = 'o.created_at >= :from';
            $params['from']  = date('Y-m-d H:i:s', (int) strtotime($from . ' 00:00:00'));
        }

        if ($to !== '' && strtotime($to) !== false) {
            $where[]       = 'o.created_at <= :to';
            $params['to']  = date('Y-m-d H:i:s', (int) strtotime($to . ' 23:59:59'));
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                "SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.customer_id WHERE $whereSql",
                $params
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT o.id, o.order_number, o.status, o.payment_status, o.total,
                            o.fulfilment_method, o.created_at,
                            u.full_name AS customer_name,
                            (SELECT GROUP_CONCAT(ph.name ORDER BY ph.name SEPARATOR ', ')
                               FROM pharmacy_orders po
                               JOIN pharmacies ph ON ph.id = po.pharmacy_id
                              WHERE po.order_id = o.id) AS pharmacy_names
                     FROM orders o
                     JOIN users u ON u.id = o.customer_id
                     WHERE $whereSql
                     ORDER BY o.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        // Tab counters, independent of the active filter — so they need their
        // own parameter bag rather than reusing the filtered one.
        $counts = ['pending' => 0, 'processing' => 0, 'delivered' => 0, 'cancelled' => 0];
        foreach (self::TAB_STATUSES as $key => $statuses) {
            $list          = [];
            $counterParams = [];
            foreach ($statuses as $i => $value) {
                $list[]                = ':c' . $i;
                $counterParams['c' . $i] = $value;
            }
            $counts[$key] = (int) $db->value(
                'SELECT COUNT(*) FROM orders WHERE status IN (' . implode(',', $list) . ')',
                $counterParams
            );
        }
        $counts['all'] = (int) $db->value('SELECT COUNT(*) FROM orders');

        $this->view('admin/orders/index', [
            'title'     => $heading,
            'paginator' => $paginator,
            'counts'    => $counts,
            'filter'    => $tab,
            'search'    => $search,
            'status'    => $status,
        ], 'layouts/dashboard');
    }
}
