<?php

declare(strict_types=1);

namespace App\Controllers\Delivery;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Session;
use App\Upload;
use App\View;

/**
 * Delivery rider workspace. A rider only ever sees deliveries assigned to
 * their own user id — the scope is derived from the session, never the request.
 */
final class DeliveryDashboardController extends Controller
{
    private DeliveryService $deliveries;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->deliveries = new DeliveryService();
    }

    // -----------------------------------------------------------------------
    //  GET /delivery/dashboard
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $userId     = (int) Auth::id();
        $profile    = $this->riderRecord();
        $db         = Database::instance();

        $stats = [
            'assigned'   => (int) $db->value('SELECT COUNT(*) FROM deliveries WHERE personnel_id = ? AND status IN ("assigned","picked_up","in_transit")', ['p' => $userId]),
            'in_transit' => (int) $db->value('SELECT COUNT(*) FROM deliveries WHERE personnel_id = ? AND status = "in_transit"', ['p' => $userId]),
            'delivered'  => (int) $db->value('SELECT COUNT(*) FROM deliveries WHERE personnel_id = ? AND status = "delivered"', ['p' => $userId]),
            'today'      => (int) $db->value('SELECT COUNT(*) FROM deliveries WHERE personnel_id = ? AND DATE(delivered_at) = CURDATE()', ['p' => $userId]),
        ];

        $earningsToday = (float) ($db->value(
            'SELECT COALESCE(SUM(delivery_fee), 0) FROM deliveries
             WHERE personnel_id = ? AND DATE(delivered_at) = CURDATE()',
            ['p' => $userId]
        ) ?? 0);

        $active = $db->all(
            'SELECT d.*, o.order_number, po.sub_order_number, ph.name AS pharmacy_name,
                    ph.address AS pharmacy_address, ph.phone AS pharmacy_phone,
                    u.full_name AS customer_name, u.phone AS customer_phone,
                    o.address_snapshot, o.customer_note
             FROM deliveries d
             JOIN orders o ON o.id = d.order_id
             JOIN pharmacies ph ON ph.id = d.pharmacy_id
             JOIN users u ON u.id = o.customer_id
             LEFT JOIN pharmacy_orders po ON po.id = d.pharmacy_order_id
             WHERE d.personnel_id = ? AND d.status IN ("assigned","picked_up","in_transit")
             ORDER BY d.id ASC',
            ['p' => $userId]
        );

        $this->view('delivery/dashboard', [
            'title'    => 'Delivery dashboard',
            'heading'  => 'My deliveries',
            'sidebar'  => View::capture('delivery/partials/sidebar'),
            'stats'    => $stats,
            'earnings' => $earningsToday,
            'deliveries' => $active,
            'profile'  => $profile,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /delivery/orders
    // -----------------------------------------------------------------------
    public function orders(Request $request): void
    {
        $userId  = (int) Auth::id();
        $status  = (string) $request->query('status', '');

        $where  = ['d.personnel_id = :p'];
        $params = ['p' => $userId];

        if (in_array($status, ['pending_assignment', 'assigned', 'picked_up', 'in_transit', 'delivered', 'failed_delivery', 'cancelled'], true)) {
            $where[]          = 'd.status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM deliveries d WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT d.*, o.order_number, po.sub_order_number, ph.name AS pharmacy_name,
                            u.full_name AS customer_name, u.phone AS customer_phone
                     FROM deliveries d
                     JOIN orders o ON o.id = d.order_id
                     JOIN pharmacies ph ON ph.id = d.pharmacy_id
                     JOIN users u ON u.id = o.customer_id
                     LEFT JOIN pharmacy_orders po ON po.id = d.pharmacy_order_id
                     WHERE {$clause}
                     ORDER BY d.id DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(20),
            $this->page()
        );

        $this->view('delivery/orders', [
            'title'     => 'My deliveries',
            'heading'   => 'All my deliveries',
            'sidebar'   => View::capture('delivery/partials/sidebar'),
            'paginator' => $paginator,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /delivery/orders/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $userId    = (int) Auth::id();
        $delivery  = $this->ownedDelivery((int) $this->param('id', $params), $userId);

        $items = Database::instance()->all(
            'SELECT product_name, quantity, image FROM order_items
             WHERE order_id = ? AND pharmacy_id = ?',
            ['order_id' => $delivery['order_id'], 'pharmacy_id' => $delivery['pharmacy_id']]
        );

        $history = Database::instance()->all(
            'SELECT * FROM delivery_status_history WHERE delivery_id = ? ORDER BY id ASC',
            ['delivery_id' => $delivery['id']]
        );

        $this->view('delivery/show', [
            'title'    => 'Delivery ' . (string) $delivery['id'],
            'heading'  => 'Delivery #' . (int) $delivery['id'],
            'sidebar'  => View::capture('delivery/partials/sidebar'),
            'delivery' => $delivery,
            'items'    => $items,
            'history'  => $history,
            'breadcrumbs' => [['label' => 'Deliveries', 'url' => '/delivery/orders'], ['label' => '#' . (int) $delivery['id']]],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /delivery/orders/{id}/status
    // -----------------------------------------------------------------------
    public function updateStatus(Request $request, array $params): void
    {
        $userId   = (int) Auth::id();
        $id       = (int) $this->param('id', $params);
        $delivery = $this->ownedDelivery($id, $userId);
        $status   = (string) $request->input('status', '');
        $note     = trim((string) $request->input('note', ''));
        $lat      = $request->input('latitude');
        $lon      = $request->input('longitude');

        $proofPath = null;
        if ($request->file('proof_image') !== null) {
            $saved = Upload::store($request->file('proof_image'), 'pod');
            if ($saved !== null) {
                $proofPath = $saved['path'];
            }
        }

        try {
            $this->deliveries->updateStatus($id, $status, [
                'note'            => $note ?: null,
                'proof_image'     => $proofPath,
                'gps_latitude'    => is_numeric($lat) ? (float) $lat : null,
                'gps_longitude'   => is_numeric($lon) ? (float) $lon : null,
                'failure_reason'  => $status === 'failed_delivery' ? ($note ?: 'Customer unavailable') : null,
            ], $userId);
        } catch (\App\HttpException $e) {
            Session::error($e->getMessage());
            Response::back('/delivery/orders/' . $id);
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
            Response::back('/delivery/orders/' . $id);
        }

        // A completed drop closes the pharmacy's slice, which in turn releases
        // the pharmacy's earnings.
        if (
            in_array($status, ['delivered', 'failed_delivery', 'cancelled'], true)
            && $delivery['pharmacy_order_id'] !== null
        ) {
            $sliceStatus = (string) Database::instance()->value(
                'SELECT status FROM pharmacy_orders WHERE id = ?',
                ['id' => $delivery['pharmacy_order_id']]
            );

            if ($status === 'delivered' && in_array($sliceStatus, ['out_for_delivery', 'ready_for_delivery'], true)) {
                (new OrderService())->updateSlice(
                    (int) $delivery['pharmacy_order_id'],
                    (int) $delivery['pharmacy_id'],
                    'deliver',
                    $note ?: 'Delivered'
                );
            }
        }

        (new AuditService())->log('delivery.' . $status, 'delivery', $id, 'Delivery marked as ' . str_replace('_', ' ', $status));

        Session::success(match ($status) {
            'picked_up'      => 'Marked as picked up. Safe travels.',
            'in_transit'     => 'Customer notified that you are on the way.',
            'delivered'      => 'Delivery confirmed. Thank you!',
            'failed_delivery' => 'Marked as a failed attempt. The pharmacy has been notified.',
            default          => 'Delivery updated.',
        });

        Response::back('/delivery/orders/' . $id);
    }

    // -----------------------------------------------------------------------
    //  GET /delivery/history
    // -----------------------------------------------------------------------
    public function history(Request $request): void
    {
        $userId    = (int) Auth::id();
        $from      = (string) $request->query('from', date('Y-m-01'));
        $to        = (string) $request->query('to', date('Y-m-d'));

        $summary = Database::instance()->first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END), 0) AS earned,
                    COALESCE(SUM(CASE WHEN status = 'failed_delivery' THEN 1 ELSE 0 END), 0) AS failed
             FROM deliveries
             WHERE personnel_id = ? AND DATE(delivered_at) BETWEEN ? AND ?",
            ['p' => $userId, 'from' => $from, 'to' => $to]
        ) ?? ['total' => 0, 'earned' => 0, 'failed' => 0];

        $rows = Database::instance()->all(
            'SELECT d.*, o.order_number, ph.name AS pharmacy_name, u.full_name AS customer_name
             FROM deliveries d
             JOIN orders o ON o.id = d.order_id
             JOIN pharmacies ph ON ph.id = d.pharmacy_id
             JOIN users u ON u.id = o.customer_id
             WHERE d.personnel_id = ? AND DATE(d.delivered_at) BETWEEN ? AND ?
             ORDER BY d.delivered_at DESC LIMIT 200',
            ['p' => $userId, 'from' => $from, 'to' => $to]
        );

        $this->view('delivery/history', [
            'title'   => 'Delivery history',
            'heading' => 'Delivery history',
            'sidebar' => View::capture('delivery/partials/sidebar'),
            'rows'    => $rows,
            'summary' => $summary,
            'from'    => $from,
            'to'      => $to,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /delivery/profile
    // -----------------------------------------------------------------------
    public function profile(Request $request): void
    {
        $userId = (int) Auth::id();
        $db     = Database::instance();

        $record = $db->first('SELECT * FROM delivery_personnel WHERE user_id = ?', ['user_id' => $userId]);

        if ($record === null) {
            $db->insert('delivery_personnel', ['user_id' => $userId, 'is_available' => 1]);
            $record = $db->first('SELECT * FROM delivery_personnel WHERE user_id = ?', ['user_id' => $userId]);
        }

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'phone'        => 'required|phone|max:32',
                    'vehicle_type' => 'nullable|in:motorcycle,bicycle,car,van,on_foot',
                    'plate_number' => 'nullable|string|max:20',
                ],
                $request->all(),
                'delivery/profile',
                '/delivery/profile'
            );

            $db->update('users', ['phone' => $data['phone']], 'id = ?', ['id' => $userId]);

            $db->update('delivery_personnel', [
                'vehicle_type'        => $data['vehicle_type'] ?? null,
                'plate_number'        => $data['plate_number'] ?? null,
                'is_available'        => $request->bool('is_available') ? 1 : 0,
                'max_active_deliveries' => (int) $request->input('max_active_deliveries', 5),
            ], 'id = ?', ['id' => $record['id']]);

            Session::success('Your rider profile has been updated.');
            Response::redirect('/delivery/profile');
        }

        $this->view('delivery/profile', [
            'title'   => 'Rider profile',
            'heading' => 'My profile',
            'sidebar' => View::capture('delivery/partials/sidebar'),
            'record'  => $record,
            'user'    => Auth::user(),
            'stats'   => $db->first(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END), 0) AS delivered,
                        COALESCE(SUM(CASE WHEN status = "delivered" THEN delivery_fee ELSE 0 END), 0) AS earned
                 FROM deliveries WHERE personnel_id = ?',
                ['p' => $userId]
            ) ?? ['total' => 0, 'delivered' => 0, 'earned' => 0],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------

    /**
     * The rider's own delivery_personnel record.
     *
     * @return array<string,mixed>|null
     */
    private function riderRecord(): ?array
    {
        return Database::instance()->first(
            'SELECT * FROM delivery_personnel WHERE user_id = ?',
            ['user_id' => Auth::id()]
        );
    }

    /**
     * Load a delivery, refusing if it is not assigned to this rider.
     *
     * @return array<string,mixed>
     */
    private function ownedDelivery(int $id, int $userId): array
    {
        $delivery = Database::instance()->first(
            'SELECT d.*, o.order_number, o.customer_note, o.address_snapshot, o.fulfilment_method,
                    o.customer_id, po.sub_order_number, ph.name AS pharmacy_name,
                    ph.address AS pharmacy_address, ph.phone AS pharmacy_phone,
                    u.full_name AS customer_name, u.phone AS customer_phone
             FROM deliveries d
             JOIN orders o ON o.id = d.order_id
             JOIN pharmacies ph ON ph.id = d.pharmacy_id
             JOIN users u ON u.id = o.customer_id
             LEFT JOIN pharmacy_orders po ON po.id = d.pharmacy_order_id
             WHERE d.id = ? AND d.personnel_id = ? LIMIT 1',
            ['id' => $id, 'p' => $userId]
        );

        if ($delivery === null) {
            throw HttpException::notFound('That delivery is not assigned to you.');
        }
        return $delivery;
    }
}
