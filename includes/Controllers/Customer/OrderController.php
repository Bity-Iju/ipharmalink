<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Services\OrderService;
use App\Session;
use App\Setting;

/**
 * Customer order history, tracking, cancellation, reorder, receipts and
 * delivery confirmation. Every query is scoped to the signed-in customer.
 */
final class OrderController extends Controller
{
    private OrderService $orders;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->orders = new OrderService();
    }

    // -----------------------------------------------------------------------
    //  GET /account/orders
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $customerId = (int) Auth::id();
        $db         = Database::instance();
        $status     = (string) $request->query('status', '');

        $allowed = [
            'pending_payment',
            'paid',
            'received',
            'processing',
            'preparing',
            'ready_for_pickup',
            'ready_for_delivery',
            'out_for_delivery',
            'delivered',
            'cancelled',
            'refunded'
        ];

        [$where, $params] = [['o.customer_id = :cust'], []];
        $params['cust'] = $customerId;

        if (in_array($status, $allowed, true)) {
            $where[]            = 'o.status = :status';
            $params['status']   = $status;
        }
        if ($request->query('q', '') !== '') {
            $where[]      = 'o.order_number LIKE :q';
            $params['q']  = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('q')) . '%';
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM orders o WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT o.id, o.order_number, o.status, o.payment_status, o.total, o.currency,
                            o.fulfilment_method, o.created_at, o.paid_at, o.completed_at,
                            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                            (SELECT GROUP_CONCAT(DISTINCT ph.name SEPARATOR ', ')
                               FROM order_items oi JOIN pharmacies ph ON ph.id = oi.pharmacy_id
                              WHERE oi.order_id = o.id) AS pharmacy_names,
                            (SELECT COUNT(*) FROM pharmacy_orders po WHERE po.order_id = o.id) AS slice_count,
                            (SELECT COUNT(*) FROM pharmacy_orders po WHERE po.order_id = o.id
                                AND po.status = 'delivered') AS delivered_slices
                     FROM orders o WHERE {$clause}
                     ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(10),
            $this->page()
        );

        $counts = [];
        foreach (
            $db->all(
                'SELECT status, COUNT(*) AS total FROM orders WHERE customer_id = ? GROUP BY status',
                ['customer_id' => $customerId]
            ) as $row
        ) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $this->view('customer/orders', [
            'title'     => 'My orders',
            'heading'   => 'My orders',
            'paginator' => $paginator,
            'status'    => $status,
            'counts'    => $counts,
            'search'    => (string) $request->query('q', ''),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /account/orders/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $order = $this->orders->findForCustomer((int) $this->param('id', $params), (int) Auth::id());
        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        $order = $this->orders->find((int) $order['id']);

        $payment = Database::instance()->first(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            ['order_id' => $order['id']]
        );

        // Estimated arrival: pharmacy prep time plus delivery time.
        $estimate = null;
        if ($order['fulfilment_method'] === 'delivery' && !in_array((string) $order['status'], ['delivered', 'cancelled'], true)) {
            $minutes = (int) Database::instance()->value(
                'SELECT COALESCE(MAX(ph.preparation_minutes + ph.estimated_delivery_minutes), 60)
                 FROM order_items oi JOIN pharmacies ph ON ph.id = oi.pharmacy_id
                 WHERE oi.order_id = ?',
                ['order_id' => $order['id']]
            );
            $estimate = date('g:i a', strtotime('+' . $minutes . ' minutes'));
        }

        $this->view('customer/order-detail', [
            'title'    => 'Order ' . (string) $order['order_number'],
            'heading'  => 'Order ' . (string) $order['order_number'],
            'order'    => $order,
            'payment'  => $payment,
            'estimate' => $estimate,
            'canCancel' => in_array((string) $order['status'], ['pending_payment', 'paid', 'received'], true),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /account/orders/{id}/cancel
    // -----------------------------------------------------------------------
    public function cancel(Request $request, array $params): void
    {
        $id       = (int) $this->param('id', $params);
        $reason   = trim((string) $request->input('reason', '')) ?: 'Cancelled by customer';
        $order    = $this->orders->findForCustomer($id, (int) Auth::id());

        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        try {
            $this->orders->cancelOrder($id, (int) Auth::id(), $reason);
            Session::success('Your order has been cancelled and any reserved stock has been returned.');
        } catch (\App\ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            Session::error(implode(' ', $messages));
        }

        Response::redirect('/account/orders/' . $id);
    }

    // -----------------------------------------------------------------------
    //  POST /account/orders/{id}/reorder
    // -----------------------------------------------------------------------
    public function reorder(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $order = $this->orders->findForCustomer($id, (int) Auth::id());
        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        $cart  = new CartService();
        $added = 0;
        $skipped = 0;

        foreach ($this->orders->items($id) as $item) {
            try {
                // Re-adding respects current stock, visibility and pricing.
                $cart->add((int) $item['product_id'], (int) $item['quantity']);
                $added++;
            } catch (\App\ValidationException) {
                $skipped++;
            }
        }

        if ($added > 0) {
            Session::success(sprintf('%d item%s added back to your cart.', $added, $added === 1 ? '' : 's'));
        }
        if ($skipped > 0) {
            Session::warning(sprintf('%d item%s no longer available and %s skipped.', $skipped, $skipped === 1 ? ' is' : 's are', $skipped === 1 ? 'was' : 'were'));
        }
        if ($added === 0) {
            Session::info('None of the items from this order are currently available.');
        }

        Response::redirect('/cart');
    }

    // -----------------------------------------------------------------------
    //  GET /account/orders/{id}/receipt
    // -----------------------------------------------------------------------
    public function receipt(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $order = $this->orders->findForCustomer($id, (int) Auth::id());
        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        $order = $this->orders->find($id);
        $user  = Auth::user();

        $this->view('customer/receipt', [
            'title'   => 'Receipt ' . (string) $order['order_number'],
            'order'   => $order,
            'user'    => $user,
            'business' => [
                'name'    => Setting::getString('general.platform_name', 'iPharmaLink'),
                'address' => Setting::getString('general.address', ''),
                'phone'   => Setting::getString('general.support_phone', ''),
                'email'   => Setting::getString('general.support_email', ''),
            ],
        ], null);
    }

    // -----------------------------------------------------------------------
    //  GET /account/orders/{id}/track
    // -----------------------------------------------------------------------
    public function track(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $order = $this->orders->findForCustomer($id, (int) Auth::id());
        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        $order = $this->orders->find($id);

        $this->view('customer/track', [
            'title'   => 'Track order ' . (string) $order['order_number'],
            'heading' => 'Track order ' . (string) $order['order_number'],
            'order'   => $order,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /account/orders/{id}/confirm-delivery
    // -----------------------------------------------------------------------
    public function confirmDelivery(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $order = $this->orders->findForCustomer($id, (int) Auth::id());
        if ($order === null) {
            throw HttpException::notFound('We could not find that order.');
        }

        // The OTP guards against a rider marking an order delivered without
        // handing anything over. Only required where an OTP was issued.
        $delivery = Database::instance()->first(
            'SELECT id, otp_code, status FROM deliveries WHERE order_id = ? LIMIT 1',
            ['order_id' => $id]
        );

        $otp = trim((string) $request->input('otp', ''));
        if ($delivery !== null && $delivery['otp_code'] !== null && $order['status'] !== 'delivered') {
            if (!hash_equals((string) $delivery['otp_code'], $otp)) {
                Session::error('That confirmation code is not correct. Please check with your rider.');
                Response::redirect('/account/orders/' . $id);
            }
        }

        Database::instance()->update('deliveries', [
            'status'                    => 'delivered',
            'delivered_at'              => date('Y-m-d H:i:s'),
            'confirmed_by_customer_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', ['id' => $delivery['id']]);

        $this->orders->reaggregate($id);

        // Any slice the rider had marked out-for-delivery is now complete.
        Database::instance()->update('pharmacy_orders', [
            'status'       => OrderService::STATUS_DELIVERED,
            'delivered_at' => date('Y-m-d H:i:s'),
        ], "order_id = ? AND status IN ('out_for_delivery','ready_for_delivery')", ['order_id' => $id]);

        foreach (
            Database::instance()->all(
                'SELECT id FROM pharmacy_orders WHERE order_id = ? AND status = ?',
                ['order_id' => $id, 'status' => OrderService::STATUS_DELIVERED]
            ) as $slice
        ) {
            (new \App\Services\WalletService())->releaseEarningsForSlice((int) $slice['id']);
        }

        (new \App\Services\NotificationService())
            ->orderStatus((int) Auth::id(), $id, (string) $order['order_number'], 'delivered', 'Confirmed by customer')
            ->send();

        Session::success('Thank you — your delivery is confirmed. You can now review the pharmacy.');
        Response::redirect('/account/orders/' . $id);
    }
}
