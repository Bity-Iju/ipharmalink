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
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Session;
use App\View;

/**
 * Pharmacy delivery screen: see the drops for this pharmacy and hand them to
 * a rider. Riders themselves work in /delivery.
 */
final class DeliveryController extends Controller
{
    private DeliveryService $deliveries;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->deliveries = new DeliveryService();
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/deliveries
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(*) FROM deliveries WHERE pharmacy_id = ?',
                ['pharmacy_id' => $pharmacyId]
            ),
            static function (Database $db, int $perPage, int $offset) use ($pharmacyId): array {
                return $db->all(
                    'SELECT d.*, o.order_number, o.fulfilment_method,
                            po.sub_order_number,
                            ph.name AS pharmacy_name, ph.address AS pharmacy_address, ph.phone AS pharmacy_phone,
                            u.full_name AS customer_name, u.phone AS customer_phone,
                            r.full_name AS rider_name, r.phone AS rider_phone
                     FROM deliveries d
                     JOIN orders o ON o.id = d.order_id
                     JOIN pharmacies ph ON ph.id = d.pharmacy_id
                     JOIN users u ON u.id = o.customer_id
                     LEFT JOIN pharmacy_orders po ON po.id = d.pharmacy_order_id
                     LEFT JOIN users r ON r.id = d.personnel_id
                     WHERE d.pharmacy_id = ?
                     ORDER BY d.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                    ['pharmacy_id' => $pharmacyId]
                );
            },
            $this->perPage(20),
            $this->page()
        );

        $counts = [];
        foreach (
            $db->all(
                'SELECT status, COUNT(*) AS total FROM deliveries WHERE pharmacy_id = ? GROUP BY status',
                ['pharmacy_id' => $pharmacyId]
            ) as $row
        ) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $this->view('pharmacy/deliveries', [
            'title'     => 'Deliveries',
            'heading'   => 'Deliveries',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
            'counts'    => $counts,
            'riders'    => $this->deliveries->availablePersonnel($pharmacyId),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/deliveries/{id}/assign
    // -----------------------------------------------------------------------
    public function assign(Request $request, array $params): void
    {
        $id         = (int) $this->param('id', $params);
        $pharmacyId = (int) Auth::pharmacyId();
        $personnelId = $request->postInt('personnel_id');

        $delivery = Database::instance()->first(
            'SELECT d.*, o.customer_id, o.fulfilment_method, po.status AS slice_status
             FROM deliveries d
             JOIN orders o ON o.id = d.order_id
             LEFT JOIN pharmacy_orders po ON po.id = d.pharmacy_order_id
             WHERE d.id = ? AND d.pharmacy_id = ? LIMIT 1',
            ['id' => $id, 'pharmacy_id' => $pharmacyId]
        );

        if ($delivery === null) {
            throw HttpException::notFound('That delivery was not found.');
        }

        if ($delivery['fulfilment_method'] === 'pickup') {
            Session::error('This order is collected by the customer, so there is nothing to deliver.');
            Response::redirect('/pharmacy/deliveries');
        }

        if ($personnelId <= 0) {
            Session::error('Please choose a delivery rider.');
            Response::redirect('/pharmacy/deliveries');
        }

        try {
            $this->deliveries->assign($id, $personnelId, $pharmacyId);
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
            Response::redirect('/pharmacy/deliveries');
        }

        // Moving to "assigned" releases the slice for dispatch.
        if (in_array((string) $delivery['slice_status'], ['ready_for_delivery', 'ready_for_pickup'], true)) {
            (new OrderService())->updateSlice(
                (int) $delivery['pharmacy_order_id'],
                $pharmacyId,
                'dispatch',
                'Assigned to a delivery rider'
            );
        }

        (new AuditService())->log('delivery.assigned', 'delivery', $id, 'Delivery assigned to personnel #' . $personnelId);

        Session::success('Rider assigned. They can now see this drop on their dashboard.');
        Response::redirect('/pharmacy/deliveries');
    }
}
