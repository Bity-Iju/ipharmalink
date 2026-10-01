<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Session;
use App\View;

/**
 * Pharmacy order queue.
 *
 * A pharmacy only ever sees its own slice of a parent order. Every lookup
 * carries `pharmacy_id = <session pharmacy>` in the WHERE clause, and the
 * slice action re-verifies ownership inside a row lock.
 */
final class PharmacyOrderController extends Controller
{
    private OrderService $orders;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->orders = new OrderService();
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/orders (+ filtered variants)
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $this->listing($request, '', 'All orders');
    }

    public function newOrders(Request $request): void
    {
        $this->listing($request, 'new', 'New orders');
    }

    public function processing(Request $request): void
    {
        $this->listing($request, 'processing', 'Processing');
    }

    public function ready(Request $request): void
    {
        $this->listing($request, 'ready', 'Ready');
    }

    public function delivered(Request $request): void
    {
        $this->listing($request, 'delivered', 'Delivered');
    }

    public function cancelled(Request $request): void
    {
        $this->listing($request, 'cancelled', 'Cancelled');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/orders/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $slice      = $this->orders->findSliceForPharmacy((int) $this->param('id', $params), $pharmacyId);

        if ($slice === null) {
            throw HttpException::notFound('That order was not found.');
        }

        $history = Database::instance()->all(
            'SELECT h.*, u.full_name AS actor_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.actor_id
             WHERE h.scope = "pharmacy_order" AND h.scope_id = ?
             ORDER BY h.id ASC',
            ['scope_id' => $slice['id']]
        );

        $prescriptions = Database::instance()->all(
            'SELECT * FROM prescriptions WHERE order_id = ? AND pharmacy_id = ? ORDER BY id DESC',
            ['order_id' => $slice['order_id'], 'pharmacy_id' => $pharmacyId]
        );

        $delivery = Database::instance()->first(
            'SELECT d.*, u.full_name AS personnel_name, u.phone AS personnel_phone
             FROM deliveries d LEFT JOIN users u ON u.id = d.personnel_id
             WHERE d.pharmacy_order_id = ? LIMIT 1',
            ['pharmacy_order_id' => $slice['id']]
        );

        $riders = Database::instance()->all(
            'SELECT u.id, u.full_name, u.phone, dp.vehicle_type, dp.plate_number
             FROM delivery_personnel dp JOIN users u ON u.id = dp.user_id
             WHERE dp.is_available = 1 AND (dp.pharmacy_id = ? OR dp.pharmacy_id IS NULL)
               AND u.status = "active"',
            ['pharmacy_id' => $pharmacyId]
        );

        // Which actions are legal from the current state.
        $available = [];
        foreach (OrderService::SLICE_ACTIONS as $action => $targets) {
            if (in_array((string) $slice['status'], $targets, true)) {
                $available[] = $action;
            }
        }

        $this->view('pharmacy/orders/show', [
            'title'         => 'Sub-order ' . (string) $slice['sub_order_number'],
            'heading'       => 'Order ' . (string) $slice['sub_order_number'],
            'sidebar'       => View::capture('pharmacy/partials/sidebar'),
            'slice'         => $slice,
            'history'       => $history,
            'prescriptions' => $prescriptions,
            'delivery'      => $delivery,
            'riders'        => $riders,
            'actions'       => $available,
            'breadcrumbs'   => [
                ['label' => 'Orders', 'url' => '/pharmacy/orders'],
                ['label' => (string) $slice['sub_order_number']],
            ],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/orders/{id}/action
    // -----------------------------------------------------------------------
    public function act(Request $request, array $params): void
    {
        $id        = (int) $this->param('id', $params);
        $action    = (string) $request->input('action', '');
        $note      = trim((string) $request->input('note', ''));
        $pharmacyId = (int) Auth::pharmacyId();

        if (!array_key_exists($action, OrderService::SLICE_ACTIONS)) {
            Session::error('That action is not recognised.');
            Response::back('/pharmacy/orders');
        }

        // Rejecting or cancelling always needs a reason for the customer.
        if (in_array($action, ['reject', 'cancel'], true) && $note === '') {
            Session::error('Please give the customer a reason for cancelling this order.');
            Response::back('/pharmacy/orders/' . $id);
        }

        try {
            $this->orders->updateSlice($id, $pharmacyId, $action, $note ?: null);
        } catch (\App\ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            Session::error(implode(' ', $messages));
            Response::back('/pharmacy/orders/' . $id);
        } catch (\RuntimeException $e) {
            Session::error($e->getMessage());
            Response::back('/pharmacy/orders/' . $id);
        }

        (new AuditService())->log('pharmacy.order.' . $action, 'pharmacy_order', $id, sprintf(
            'Sub-order #%d %s by pharmacy',
            $id,
            str_replace('_', ' ', $action)
        ));

        Session::success(match ($action) {
            'accept'   => 'Order accepted. Start preparing it now.',
            'reject'   => 'Order rejected and the customer has been notified.',
            'prepare'  => 'Order marked as being prepared.',
            'ready'    => 'Order marked as ready.',
            'dispatch' => 'Order dispatched.',
            'deliver'  => 'Order marked as delivered. Earnings have been released.',
            'cancel'   => 'Order cancelled and stock restored.',
            default    => 'Order updated.',
        });

        Response::back('/pharmacy/orders/' . $id);
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/prescriptions
    // -----------------------------------------------------------------------
    public function prescriptions(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(*) FROM prescriptions WHERE pharmacy_id = ?',
                ['pharmacy_id' => $pharmacyId]
            ),
            static function (Database $db, int $perPage, int $offset) use ($pharmacyId): array {
                return $db->all(
                    'SELECT p.*, u.full_name AS customer_name, u.phone AS customer_phone, o.order_number
                     FROM prescriptions p
                     JOIN users u ON u.id = p.user_id
                     LEFT JOIN orders o ON o.id = p.order_id
                     WHERE p.pharmacy_id = ?
                     ORDER BY (p.status = "pending") DESC, p.id DESC
                     LIMIT ' . $perPage . ' OFFSET ' . $offset,
                    ['pharmacy_id' => $pharmacyId]
                );
            },
            $this->perPage(15),
            $this->page()
        );

        $this->view('pharmacy/orders/prescriptions', [
            'title'     => 'Prescription review',
            'heading'   => 'Prescription review',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/prescriptions/{id}
    // -----------------------------------------------------------------------
    public function reviewPrescription(Request $request, array $params): void
    {
        $id          = (int) $this->param('id', $params);
        $pharmacyId  = (int) Auth::pharmacyId();
        $status      = (string) $request->input('status', 'approved');
        $note        = trim((string) $request->input('review_note', ''));

        if (!in_array($status, ['approved', 'rejected'], true)) {
            $status = 'approved';
        }
        if ($status === 'rejected' && $note === '') {
            Session::error('Please tell the customer why the prescription was not accepted.');
            Response::back('/pharmacy/prescriptions');
        }

        $db  = Database::instance();
        $row = $db->first(
            'SELECT * FROM prescriptions WHERE id = ? AND pharmacy_id = ? LIMIT 1',
            ['id' => $id, 'pharmacy_id' => $pharmacyId]
        );
        if ($row === null) {
            throw HttpException::notFound('That prescription was not found.');
        }

        $db->transaction(function () use ($db, $id, $row, $status, $note, $pharmacyId): void {
            $db->update('prescriptions', [
                'status'        => $status,
                'review_note'   => $note ?: null,
                'reviewed_by'   => Auth::id(),
                'reviewed_at'   => date('Y-m-d H:i:s'),
            ], 'id = ?', ['id' => $id]);

            // Reflect the decision on the ordered lines so the fulfilment
            // screens show the pharmacist's verdict.
            $db->update(
                'order_items',
                [
                    'prescription_status' => $status,
                ],
                'order_id = ? AND pharmacy_id = ? AND requires_prescription = 1',
                ['order_id' => $row['order_id'], 'pharmacy_id' => $pharmacyId]
            );
        });

        (new NotificationService())->to(
            (int) $row['user_id'],
            'prescription.' . $status,
            $status === 'approved' ? 'Prescription approved' : 'Prescription needs attention',
            $status === 'approved'
                ? 'Your prescription has been reviewed and approved by our pharmacist.'
                : $note,
            '/account/orders/' . $row['order_id'],
            'prescription',
            $id
        )->send();

        (new AuditService())->log('prescription.' . $status, 'prescription', $id, 'Prescription reviewed by pharmacist');

        Session::success($status === 'approved'
            ? 'Prescription approved. You can now dispense the item.'
            : 'Prescription rejected and the customer has been informed.');

        Response::back('/pharmacy/prescriptions');
    }

    // -----------------------------------------------------------------------
    //  Shared listing
    // -----------------------------------------------------------------------

    private function listing(Request $request, string $filter, string $heading): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();
        $search     = trim((string) $request->query('q', ''));
        $status     = (string) $request->query('status', '');

        $where  = ['po.pharmacy_id = :pharm'];
        $params = ['pharm' => $pharmacyId];

        if ($filter === 'new') {
            $where[] = "po.status IN ('paid','received')";
        } elseif ($filter === 'processing') {
            $where[] = "po.status IN ('processing','preparing')";
        } elseif ($filter === 'ready') {
            $where[] = "po.status IN ('ready_for_pickup','ready_for_delivery')";
        } elseif ($filter === 'delivered') {
            $where[] = "po.status = 'delivered'";
        } elseif ($filter === 'cancelled') {
            $where[] = "po.status IN ('cancelled','refunded')";
        } elseif (in_array($status, [
            'paid',
            'received',
            'processing',
            'preparing',
            'ready_for_pickup',
            'ready_for_delivery',
            'out_for_delivery',
            'delivered',
            'cancelled',
            'refunded',
            'pending_payment'
        ], true)) {
            $where[]           = 'po.status = :status';
            $params['status']  = $status;
        }

        if ($search !== '') {
            $where[]      = '(po.sub_order_number LIKE :q1 OR o.order_number LIKE :q2 OR u.full_name LIKE :q3 OR u.phone LIKE :q4)';
            $like         = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM pharmacy_orders po
                 JOIN orders o ON o.id = po.order_id
                 JOIN users u ON u.id = o.customer_id
                 WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT po.*, o.order_number, o.fulfilment_method, o.payment_status, o.customer_note,
                            u.full_name AS customer_name, u.phone AS customer_phone,
                            (SELECT COUNT(*) FROM order_items oi
                              WHERE oi.order_id = po.order_id AND oi.pharmacy_id = po.pharmacy_id) AS item_count,
                            (SELECT COUNT(*) FROM order_items oi
                              WHERE oi.order_id = po.order_id AND oi.pharmacy_id = po.pharmacy_id
                                AND oi.requires_prescription = 1 AND oi.prescription_status = 'pending') AS rx_pending,
                            d.status AS delivery_status, d.tracking_number
                     FROM pharmacy_orders po
                     JOIN orders o ON o.id = po.order_id
                     JOIN users u ON u.id = o.customer_id
                     LEFT JOIN deliveries d ON d.pharmacy_order_id = po.id
                     WHERE {$clause}
                     ORDER BY po.id DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(15),
            $this->page()
        );

        $counts = [];
        foreach (
            $db->all(
                'SELECT status, COUNT(*) AS total FROM pharmacy_orders WHERE pharmacy_id = ? GROUP BY status',
                ['pharmacy_id' => $pharmacyId]
            ) as $row
        ) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $this->view('pharmacy/orders/index', [
            'title'     => $heading,
            'heading'   => $heading,
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
            'counts'    => $counts,
            'filter'    => $filter,
            'status'    => $status,
            'search'    => $search,
            'breadcrumbs' => $filter === '' ? [] : [['label' => 'Orders', 'url' => '/pharmacy/orders'], ['label' => $heading]],
        ], 'layouts/dashboard');
    }
}
