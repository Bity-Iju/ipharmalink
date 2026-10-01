<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Session;

/**
 * Admin people management: end customers, pharmacy owners/staff and the
 * delivery personnel roster.
 *
 * Customer records are platform-wide, so an admin may always see them — but
 * the listing never exposes anything belonging to another pharmacy's account
 * beyond what the platform itself needs to moderate the relationship.
 */
final class CustomerAdminController extends Controller
{
    private const PER_PAGE = 20;

    // -----------------------------------------------------------------------
    //  GET /admin/customers
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $search = $request->trimmed('q');
        $status = $request->trimmed('status');

        $paginator = $this->peopleQuery(
            "u.role_id = (SELECT id FROM roles WHERE name = 'customer')",
            $search,
            $status
        );

        $this->view('admin/customers/index', [
            'title'     => 'Customers',
            'paginator' => $paginator,
            'search'    => $search,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/customers/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $customer = $this->person((int) $this->param('id', $params), 'customer');
        $db       = Database::instance();

        $orders = $db->all(
            'SELECT o.id, o.order_number, o.status, o.payment_status, o.total, o.placed_at,
                    (SELECT COUNT(*) FROM pharmacy_orders po WHERE po.order_id = o.id) AS pharmacy_count
             FROM orders o
             WHERE o.customer_id = ?
             ORDER BY o.created_at DESC LIMIT 25',
            ['customer_id' => $customer['id']]
        );

        $payments = $db->all(
            'SELECT id, gateway_code, amount, status, reference, paid_at
             FROM payments
             WHERE customer_id = ?
             ORDER BY created_at DESC LIMIT 25',
            ['customer_id' => $customer['id']]
        );

        $addresses = $db->all(
            'SELECT label, recipient_name, phone, address, city, state, is_default
             FROM user_addresses
             WHERE user_id = ?
             ORDER BY is_default DESC, id DESC LIMIT 10',
            ['user_id' => $customer['id']]
        );

        // Which pharmacies this customer has actually transacted with.
        $pharmacies = $db->all(
            'SELECT ph.id, ph.name, ph.slug, ph.city, ph.state,
                    COUNT(DISTINCT o.id) AS orders,
                    COALESCE(SUM(o.total), 0) AS spent
             FROM orders o
             JOIN pharmacy_orders po ON po.order_id = o.id
             JOIN pharmacies ph      ON ph.id = po.pharmacy_id
             WHERE o.customer_id = ?
             GROUP BY ph.id, ph.name, ph.slug, ph.city, ph.state
             ORDER BY spent DESC',
            ['customer_id' => $customer['id']]
        );

        $stats = [
            'orders'     => (int) $db->value('SELECT COUNT(*) FROM orders WHERE customer_id = ?', ['customer_id' => $customer['id']]),
            'spent'      => (float) $db->value(
                "SELECT COALESCE(SUM(total), 0) FROM orders WHERE customer_id = ? AND status NOT IN ('cancelled','refunded')",
                ['customer_id' => $customer['id']]
            ),
            'reviews'    => (int) $db->value('SELECT COUNT(*) FROM reviews WHERE user_id = ?', ['user_id' => $customer['id']]),
            'wishlist'   => (int) $db->value(
                'SELECT COUNT(*) FROM wishlist_items wi
                 JOIN wishlists w ON w.id = wi.wishlist_id
                 WHERE w.user_id = ?',
                ['user_id' => $customer['id']]
            ),
        ];

        $this->view('admin/customers/show', [
            'title'     => $customer['full_name'],
            'customer'  => $customer,
            'orders'    => $orders,
            'payments'  => $payments,
            'addresses' => $addresses,
            'pharmacies' => $pharmacies,
            'stats'     => $stats,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/customers/{id}/status
    // -----------------------------------------------------------------------
    public function updateStatus(Request $request, array $params): void
    {
        $customer = $this->person((int) $this->param('id', $params), 'customer');
        $status   = $request->trimmed('status');

        $allowed = ['active', 'pending', 'suspended', 'deactivated'];
        if (!in_array($status, $allowed, true)) {
            throw new HttpException(422, 'Choose a valid account status.');
        }

        // A suspended account must not keep an active session.
        Database::instance()->update('users', ['status' => $status], 'id = :id', ['id' => $customer['id']]);

        (new AuditService($request))->logChange(
            'admin.customer.status_changed',
            'user',
            (int) $customer['id'],
            ['status' => $customer['status']],
            ['status' => $status]
        );

        Session::success(sprintf('%s is now %s.', $customer['full_name'], $status));
        Response::back('/admin/customers');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/staff
    // -----------------------------------------------------------------------
    public function staff(Request $request): void
    {
        $search = $request->trimmed('q');

        // Everyone who is not an end customer, minus the seeded super admin.
        $paginator = $this->peopleQuery(
            "u.role_id <> (SELECT id FROM roles WHERE name = 'customer')
             AND u.id <> (SELECT COALESCE(MAX(id), 0) FROM users
                          WHERE role_id = (SELECT id FROM roles WHERE name = 'admin'))",
            $search,
            ''
        );

        $this->view('admin/users/staff', [
            'title'     => 'Staff',
            'paginator' => $paginator,
            'search'    => $search,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/delivery-personnel
    // -----------------------------------------------------------------------
    public function deliveryPersonnel(Request $request): void
    {
        $db        = Database::instance();
        $perPage   = $this->perPage(self::PER_PAGE);
        $page      = $this->page();

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                'SELECT COUNT(*)
                 FROM delivery_personnel dp
                 JOIN users u ON u.id = dp.user_id
                 WHERE u.deleted_at IS NULL'
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    'SELECT u.id, u.full_name, u.email, u.phone, u.status,
                            dp.vehicle_type, dp.plate_number, dp.is_available, dp.pharmacy_id,
                            (SELECT COUNT(*) FROM deliveries de
                              WHERE de.personnel_id = dp.id
                                AND de.status NOT IN (\'delivered\',\'failed\',\'cancelled\')) AS active_deliveries
                     FROM delivery_personnel dp
                     JOIN users u ON u.id = dp.user_id
                     WHERE u.deleted_at IS NULL
                     ORDER BY u.full_name ASC
                     LIMIT ' . $limit . ' OFFSET ' . $offset
                );
            },
            $perPage,
            $page
        );

        $this->view('admin/users/delivery-personnel', [
            'title'     => 'Delivery personnel',
            'paginator' => $paginator,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/delivery-personnel/create
    // -----------------------------------------------------------------------
    public function createDeliveryPersonnel(Request $request): void
    {
        $db = Database::instance();

        $pharmacies = $db->all(
            "SELECT id, name FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL ORDER BY name ASC"
        );

        $this->view('admin/users/delivery-create', [
            'title'     => 'Add delivery personnel',
            'pharmacies' => $pharmacies,
            'roles'     => $db->all("SELECT id, name FROM roles WHERE name IN ('delivery')"),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  Shared query
    // -----------------------------------------------------------------------

    /**
     * People listing shared by the customer grid and the staff grid.
     */
    private function peopleQuery(string $roleClause, string $search, string $status): Paginator
    {
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = [$roleClause, 'u.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(u.full_name LIKE :search OR u.email LIKE :search2 OR u.phone LIKE :search3
                          OR ph.name LIKE :search4)';
            $params += [
                'search'  => '%' . $search . '%',
                'search2' => '%' . $search . '%',
                'search3' => '%' . $search . '%',
                'search4' => '%' . $search . '%',
            ];
        }

        if ($status !== '') {
            $where[]        = 'u.status = :status';
            $params['status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        return Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                "SELECT COUNT(*)
                 FROM users u
                 LEFT JOIN pharmacies ph ON ph.owner_id = u.id
                 WHERE $whereSql",
                $params
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.last_login_at,
                            r.label AS role_label, r.name AS role_name,
                            ph.id AS pharmacy_id, ph.name AS pharmacy_name,
                            (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS order_count,
                            (SELECT COALESCE(SUM(o.total), 0) FROM orders o
                              WHERE o.customer_id = u.id AND o.status NOT IN ('cancelled','refunded')) AS spent
                     FROM users u
                     JOIN roles r      ON r.id = u.role_id
                     LEFT JOIN pharmacies ph ON ph.owner_id = u.id
                     WHERE $whereSql
                     ORDER BY u.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );
    }

    /**
     * Load a user that must hold the given role, or fail closed with a 404.
     *
     * @return array<string,mixed>
     */
    private function person(int $id, string $role): array
    {
        $row = Database::instance()->first(
            'SELECT u.*, r.name AS role_name, r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.name = ? AND u.deleted_at IS NULL',
            ['id' => $id, 'role' => $role]
        );

        if ($row === null) {
            throw new HttpException(404, 'That account was not found.');
        }

        return $row;
    }
}
