<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\WalletService;
use App\Session;
use App\Upload;
use App\View;

/**
 * Admin pharmacy management: the verification queue, suspension, per-pharmacy
 * drill-down into documents, products, orders, sales and activity.
 */
final class PharmacyAdminController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /admin/pharmacies
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $this->listing($request, '', 'Pharmacies');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/pharmacies/pending
    // -----------------------------------------------------------------------
    public function pending(Request $request): void
    {
        $this->listing($request, 'pending', 'Pending verification');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/pharmacies/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));
        $db       = Database::instance();

        $documents = $db->all(
            'SELECT * FROM pharmacy_documents WHERE pharmacy_id = ? ORDER BY id ASC',
            ['pharmacy_id' => $pharmacy['id']]
        );

        $stats = $db->first(
            "SELECT (SELECT COUNT(*) FROM products WHERE pharmacy_id = :p1 AND deleted_at IS NULL) AS products,
                    (SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = :p2) AS orders,
                    (SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = :p3 AND status = 'delivered') AS delivered,
                    (SELECT COALESCE(SUM(total), 0) FROM pharmacy_orders WHERE pharmacy_id = :p4) AS revenue,
                    (SELECT COALESCE(SUM(pharmacy_earnings), 0) FROM pharmacy_orders
                       WHERE pharmacy_id = :p5 AND status = 'delivered') AS earnings,
                    (SELECT COUNT(DISTINCT o.customer_id) FROM pharmacy_orders po
                       JOIN orders o ON o.id = po.order_id WHERE po.pharmacy_id = :p6) AS customers,
                    (SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE pharmacy_id = :p7) AS commission",
            [
                'p1' => $pharmacy['id'],
                'p2' => $pharmacy['id'],
                'p3' => $pharmacy['id'],
                'p4' => $pharmacy['id'],
                'p5' => $pharmacy['id'],
                'p6' => $pharmacy['id'],
                'p7' => $pharmacy['id'],
            ]
        ) ?? ['products' => 0, 'orders' => 0, 'delivered' => 0, 'revenue' => 0, 'earnings' => 0, 'customers' => 0, 'commission' => 0];

        $wallet = $db->first('SELECT * FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacy['id']])
            ?? ['balance' => 0, 'pending_balance' => 0, 'total_earned' => 0, 'total_paid' => 0];

        $this->view('admin/pharmacies/show', [
            'title'     => (string) $pharmacy['name'],
            'heading'   => (string) $pharmacy['name'],
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'documents' => $documents,
            'stats'     => $stats,
            'wallet'    => $wallet,
            'settings'  => $db->all(
                'SELECT * FROM pharmacy_settings WHERE pharmacy_id = ?',
                ['pharmacy_id' => $pharmacy['id']]
            ),
            'breadcrumbs' => [['label' => 'Pharmacies', 'url' => '/admin/pharmacies'], ['label' => (string) $pharmacy['name']]],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/pharmacies/{id}/status
    // -----------------------------------------------------------------------
    public function updateStatus(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));
        $status   = (string) $request->input('status', '');
        $reason   = trim((string) $request->input('reason', ''));

        $allowed = ['pending', 'approved', 'suspended', 'rejected', 'deactivated'];
        if (!in_array($status, $allowed, true)) {
            Session::error('That status is not valid.');
            Response::back('/admin/pharmacies');
        }

        // A rejection or suspension must explain itself to the vendor.
        if (in_array($status, ['rejected', 'suspended', 'deactivated'], true) && $reason === '') {
            Session::error('Please give a reason — the pharmacy will see it.');
            Response::back('/admin/pharmacies/' . $pharmacy['id']);
        }

        $db = Database::instance();
        $now = date('Y-m-d H:i:s');

        $db->transaction(function () use ($db, $pharmacy, $status, $reason, $now, $request): void {
            $db->update('pharmacies', [
                'status'       => $status,
                'status_reason' => $reason ?: null,
                'reviewed_by'  => Auth::id(),
                'reviewed_at'  => $now,
            ], 'id = ?', ['id' => $pharmacy['id']]);

            // Approving a pharmacy makes its products visible immediately.
            if ($status === 'approved') {
                $db->run('UPDATE pharmacy_documents SET review_status = "approved" WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacy['id']]);
            }

            // Suspending hides its products from the storefront.
            if (in_array($status, ['suspended', 'deactivated', 'rejected'], true)) {
                $db->run('UPDATE products SET is_visible = 0 WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacy['id']]);
            }
            if ($status === 'approved') {
                $db->run('UPDATE products SET is_visible = 1 WHERE pharmacy_id = ? AND is_active = 1', ['pharmacy_id' => $pharmacy['id']]);
            }

            // Commission override, set from the review screen.
            $rate = $request->input('commission_rate');
            if ($rate !== null && $rate !== '' && is_numeric($rate)) {
                $db->update('pharmacies', ['commission_rate' => (float) $rate], 'id = ?', ['id' => $pharmacy['id']]);
            }
        });

        (new AuditService())->log('pharmacy.' . $status, 'pharmacy', (int) $pharmacy['id'], sprintf(
            'Pharmacy "%s" %s%s',
            (string) $pharmacy['name'],
            $status,
            $reason !== '' ? ': ' . $reason : ''
        ));

        // Tell the vendor, whatever the outcome.
        $messages = [
            'approved'    => ['Your pharmacy has been approved', 'You can now list products and start taking orders. Welcome aboard!'],
            'suspended'   => ['Your pharmacy account has been suspended', $reason],
            'rejected'    => ['Your pharmacy registration was not approved', $reason],
            'deactivated' => ['Your pharmacy account has been deactivated', $reason],
            'pending'     => ['Your pharmacy is pending review again', 'Our compliance team will take another look.'],
        ];
        [$title, $body] = $messages[$status];

        $notifications = new NotificationService();
        $notifications->toPharmacy((int) $pharmacy['id'], 'pharmacy.' . $status, $title, $body, '/pharmacy/dashboard');
        $notifications->send();

        (new MailService())->sendPharmacyDecision(
            (string) $pharmacy['email'],
            (string) $pharmacy['name'],
            $status,
            $reason
        );

        Session::success(sprintf('"%s" is now %s.', (string) $pharmacy['name'], $status));
        Response::redirect('/admin/pharmacies/' . (int) $pharmacy['id']);
    }

    // -----------------------------------------------------------------------
    //  POST /admin/pharmacies/{id}
    // -----------------------------------------------------------------------
    public function update(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));

        $data = $this->validate(
            [
                'name'        => 'required|string|min:3|max:150',
                'phone'       => 'required|phone|max:32',
                'email'       => 'required|email|max:190',
                'city'        => 'required|string|min:2|max:80',
                'state'       => 'required|string|min:2|max:80',
                'address'     => 'required|string|min:5|max:255',
                'commission_rate' => 'nullable|decimal|between:0,50',
            ],
            $request->all(),
            'admin/pharmacies/show',
            '/admin/pharmacies/' . $pharmacy['id']
        );

        $db = Database::instance();
        $db->update('pharmacies', [
            'name'            => $data['name'],
            'phone'           => $data['phone'],
            'email'           => strtolower((string) $data['email']),
            'city'            => $data['city'],
            'state'           => $data['state'],
            'address'         => $data['address'],
            'commission_rate' => isset($data['commission_rate']) ? (float) $data['commission_rate'] : null,
            'is_featured'     => $request->bool('is_featured') ? 1 : 0,
        ], 'id = ?', ['id' => $pharmacy['id']]);

        if ($request->file('logo') !== null) {
            $saved = Upload::store($request->file('logo'), 'pharmacy');
            if ($saved !== null) {
                $db->update('pharmacies', ['logo' => $saved['path']], 'id = ?', ['id' => $pharmacy['id']]);
            }
        }

        (new AuditService())->log('pharmacy.updated', 'pharmacy', (int) $pharmacy['id'], 'Pharmacy record edited by admin');

        Session::success('Pharmacy updated.');
        Response::redirect('/admin/pharmacies/' . (int) $pharmacy['id']);
    }

    // -----------------------------------------------------------------------
    //  Drill-downs
    // -----------------------------------------------------------------------

    public function products(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));

        $products = Database::instance()->all(
            'SELECT p.*, c.name AS category_name,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.pharmacy_id = ? AND p.deleted_at IS NULL
             ORDER BY p.id DESC LIMIT 200',
            ['pharmacy_id' => $pharmacy['id']]
        );

        $this->view('admin/pharmacies/products', [
            'title'     => 'Products — ' . (string) $pharmacy['name'],
            'heading'   => 'Products',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'products'  => $products,
            'breadcrumbs' => [
                ['label' => 'Pharmacies', 'url' => '/admin/pharmacies'],
                ['label' => (string) $pharmacy['name'], 'url' => '/admin/pharmacies/' . (int) $pharmacy['id']],
                ['label' => 'Products'],
            ],
        ], 'layouts/dashboard');
    }

    public function orders(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));

        $orders = Database::instance()->all(
            'SELECT po.*, o.order_number, o.payment_status, o.fulfilment_method,
                    u.full_name AS customer_name, u.phone AS customer_phone
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             JOIN users u ON u.id = o.customer_id
             WHERE po.pharmacy_id = ?
             ORDER BY po.id DESC LIMIT 200',
            ['pharmacy_id' => $pharmacy['id']]
        );

        $this->view('admin/pharmacies/orders', [
            'title'     => 'Orders — ' . (string) $pharmacy['name'],
            'heading'   => 'Orders',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'orders'    => $orders,
            'breadcrumbs' => [
                ['label' => 'Pharmacies', 'url' => '/admin/pharmacies'],
                ['label' => (string) $pharmacy['name'], 'url' => '/admin/pharmacies/' . (int) $pharmacy['id']],
                ['label' => 'Orders'],
            ],
        ], 'layouts/dashboard');
    }

    public function sales(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));

        $rows = Database::instance()->all(
            "SELECT DATE(po.created_at) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(po.total), 0) AS revenue,
                    COALESCE(SUM(po.platform_fee), 0) AS commission,
                    COALESCE(SUM(po.pharmacy_earnings), 0) AS earnings
             FROM pharmacy_orders po
             WHERE po.pharmacy_id = ? AND po.status NOT IN ('cancelled','refunded')
             GROUP BY DATE(po.created_at) ORDER BY day DESC LIMIT 180",
            ['pharmacy_id' => $pharmacy['id']]
        );

        $commissionRate = $pharmacy['commission_rate'] ?? \App\Setting::getFloat('commission.default_rate_percent', 8.0);

        $this->view('admin/pharmacies/sales', [
            'title'     => 'Sales — ' . (string) $pharmacy['name'],
            'heading'   => 'Sales & commission',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'rows'      => $rows,
            'commissionRate' => (float) $commissionRate,
            'breadcrumbs' => [
                ['label' => 'Pharmacies', 'url' => '/admin/pharmacies'],
                ['label' => (string) $pharmacy['name'], 'url' => '/admin/pharmacies/' . (int) $pharmacy['id']],
                ['label' => 'Sales'],
            ],
        ], 'layouts/dashboard');
    }

    public function logs(Request $request, array $params): void
    {
        $pharmacy = $this->pharmacy((int) $this->param('id', $params));

        $logs = Database::instance()->all(
            "SELECT a.* FROM audit_logs a
             WHERE (a.entity_type = 'pharmacy' AND a.entity_id = :p1)
                OR (a.entity_type = 'pharmacy_order' AND a.entity_id IN
                    (SELECT id FROM pharmacy_orders WHERE pharmacy_id = :p2))
                OR (a.entity_type = 'product' AND a.entity_id IN
                    (SELECT id FROM products WHERE pharmacy_id = :p3))
             ORDER BY a.id DESC LIMIT 200",
            ['p1' => $pharmacy['id'], 'p2' => $pharmacy['id'], 'p3' => $pharmacy['id']]
        );

        $this->view('admin/pharmacies/logs', [
            'title'     => 'Activity — ' . (string) $pharmacy['name'],
            'heading'   => 'Activity log',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'logs'      => $logs,
            'breadcrumbs' => [
                ['label' => 'Pharmacies', 'url' => '/admin/pharmacies'],
                ['label' => (string) $pharmacy['name'], 'url' => '/admin/pharmacies/' . (int) $pharmacy['id']],
                ['label' => 'Activity'],
            ],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------

    private function listing(Request $request, string $filter, string $heading): void
    {
        $db     = Database::instance();
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $where  = ['ph.deleted_at IS NULL'];
        $params = [];

        if ($filter === 'pending') {
            $where[] = "ph.status = 'pending'";
        } elseif (in_array($status, ['pending', 'approved', 'suspended', 'rejected', 'deactivated'], true)) {
            $where[]           = 'ph.status = :status';
            $params['status']  = $status;
        }

        if ($search !== '') {
            $where[]      = '(ph.name LIKE :q1 OR ph.city LIKE :q2 OR ph.email LIKE :q3 OR ph.registration_number LIKE :q4 OR u.full_name LIKE :q5)';
            $like         = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM pharmacies ph LEFT JOIN users u ON u.id = ph.owner_id WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT ph.*, u.full_name AS owner_name, u.email AS owner_email,
                            (SELECT COUNT(*) FROM products p2 WHERE p2.pharmacy_id = ph.id AND p2.deleted_at IS NULL) AS product_count,
                            (SELECT COUNT(*) FROM pharmacy_orders po WHERE po.pharmacy_id = ph.id) AS order_count,
                            (SELECT COALESCE(SUM(total), 0) FROM pharmacy_orders po WHERE po.pharmacy_id = ph.id
                               AND po.status NOT IN ('cancelled','refunded')) AS revenue,
                            (SELECT COUNT(*) FROM pharmacy_documents pd WHERE pd.pharmacy_id = ph.id) AS document_count
                     FROM pharmacies ph LEFT JOIN users u ON u.id = ph.owner_id
                     WHERE {$clause}
                     ORDER BY ph.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(15),
            $this->page()
        );

        $counts = [];
        foreach (
            $db->all(
                'SELECT status, COUNT(*) AS total FROM pharmacies WHERE deleted_at IS NULL GROUP BY status'
            ) as $row
        ) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $this->view('admin/pharmacies/index', [
            'title'     => $heading,
            'heading'   => $heading,
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'paginator' => $paginator,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
            'filter'    => $filter,
            'breadcrumbs' => $filter === 'pending'
                ? [['label' => 'Pharmacies', 'url' => '/admin/pharmacies'], ['label' => 'Pending']]
                : [],
        ], 'layouts/dashboard');
    }

    /** @return array<string,mixed> */
    private function pharmacy(int $id): array
    {
        $pharmacy = Database::instance()->first(
            'SELECT ph.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
             FROM pharmacies ph LEFT JOIN users u ON u.id = ph.owner_id
             WHERE ph.id = ? LIMIT 1',
            ['id' => $id]
        );

        if ($pharmacy === null) {
            throw HttpException::notFound('That pharmacy was not found.');
        }
        return $pharmacy;
    }
}
