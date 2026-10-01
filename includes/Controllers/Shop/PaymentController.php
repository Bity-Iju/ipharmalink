<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\Payments\PaymentManager;
use App\Session;

/**
 * Payment handling.
 *
 * Security rules enforced here, without exception:
 *   1. The browser return URL NEVER marks an order paid. It only re-checks
 *      status and, at the customer's request, asks the gateway to verify.
 *   2. Webhooks are accepted only after the gateway's signature check.
 *   3. An order can only be paid for by the customer who placed it.
 */
final class PaymentController extends Controller
{
    private PaymentManager $payments;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->payments = new PaymentManager();
    }

    // -----------------------------------------------------------------------
    //  GET /payment?order=
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $orderId = $this->resolveOrderId($request);
        $order   = $this->ownedOrder($orderId);

        if ($order['payment_status'] === 'successful') {
            Response::redirect('/account/orders/' . $orderId);
        }

        $this->view('shop/payment', [
            'title'       => 'Complete payment for order ' . (string) $order['order_number'],
            'heading'     => 'Choose how to pay',
            'order'       => $order,
            'gateways'    => $this->payments->availableGateways(),
            'existing'    => $this->payments->paymentForOrder($orderId),
            'orderNumber' => (string) $order['order_number'],
        ]);
    }

    // -----------------------------------------------------------------------
    //  POST /payment/start
    // -----------------------------------------------------------------------
    public function start(Request $request): void
    {
        $orderId  = $this->resolveOrderId($request);
        $order    = $this->ownedOrder($orderId);
        $gateway  = (string) $request->input('gateway', '');

        if ($gateway === '') {
            Session::error('Please choose a payment method.');
            Response::redirect('/payment?order=' . $orderId);
        }

        try {
            $result = $this->payments->createPayment($orderId, (int) Auth::id(), $gateway);
        } catch (HttpException $e) {
            Session::error($e->getMessage());
            Response::redirect('/payment?order=' . $orderId);
        }

        // Offline methods have nothing to redirect to — show the instructions.
        if ($result['redirect_url'] === '' || $result['redirect_url'] === '#') {
            Session::info('Follow the transfer instructions below, then mark the payment as sent.');
            Response::redirect('/payment?order=' . $orderId . '&method=' . urlencode($gateway));
        }

        Response::redirect($result['redirect_url']);
    }

    // -----------------------------------------------------------------------
    //  GET /payment/return  (gateway callback — verify, never trust)
    // -----------------------------------------------------------------------
    public function return(Request $request): void
    {
        $reference = (string) $request->query('reference', $request->query('tx_ref', ''));

        if ($reference === '') {
            Response::redirect('/account/orders');
        }

        $payment = Database::instance()->first(
            'SELECT order_id FROM payments WHERE reference = ? LIMIT 1',
            ['reference' => $reference]
        );

        if ($payment === null) {
            Session::error('We could not match that payment to an order.');
            Response::redirect('/account/orders');
        }

        $orderId = (int) $payment['order_id'];
        $this->ownedOrder($orderId);   // ownership check

        // Ask the gateway what really happened (server-to-server).
        $result = $this->payments->confirmPayment($reference, $orderId);

        if ($result['paid']) {
            Response::redirect('/payment/success?order=' . $orderId);
        }

        Session::error($result['message']);
        Response::redirect('/payment/failed?order=' . $orderId);
    }

    // -----------------------------------------------------------------------
    //  POST /payment/verify  (manual "I have paid" for offline methods)
    // -----------------------------------------------------------------------
    public function verify(Request $request): void
    {
        $orderId = $this->resolveOrderId($request);
        $this->ownedOrder($orderId);

        $payment = Database::instance()->first(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            ['order_id' => $orderId]
        );

        if ($payment === null) {
            Session::error('There is no payment record for this order yet.');
            Response::redirect('/payment?order=' . $orderId);
        }

        // Offline methods carry no gateway to confirm with, so the recorded
        // reference is checked against the payment row the platform created.
        $result = $this->payments->confirmPayment((string) $payment['reference'], $orderId);

        if ($result['paid']) {
            Response::redirect('/payment/success?order=' . $orderId);
        }

        Session::error($result['message']);
        Response::redirect('/payment/failed?order=' . $orderId);
    }

    // -----------------------------------------------------------------------
    //  GET /payment/success
    // -----------------------------------------------------------------------
    public function success(Request $request): void
    {
        $orderId = $this->resolveOrderId($request);
        $order   = $this->ownedOrder($orderId);
        $status  = $this->payments->orderPaymentStatus($orderId);

        // Show the truth, not what the gateway implied by redirecting here.
        if ($status !== 'successful') {
            Session::info('We are still confirming your payment. This page will update once the gateway responds.');
        } else {
            Session::success('Payment received. Thank you for your order!');
        }

        $this->view('shop/payment-success', [
            'title'      => $status === 'successful' ? 'Payment successful' : 'Confirming your payment',
            'heading'    => $status === 'successful' ? 'Payment successful' : 'Confirming your payment',
            'order'      => $order,
            'confirmed'  => $status === 'successful',
        ]);
    }

    // -----------------------------------------------------------------------
    //  GET /payment/failed
    // -----------------------------------------------------------------------
    public function failed(Request $request): void
    {
        $orderId = $this->resolveOrderId($request);
        $order   = $this->ownedOrder($orderId);

        $this->view('shop/payment-failed', [
            'title'   => 'Payment not completed',
            'heading' => 'Payment not completed',
            'order'   => $order,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Webhooks (public, signature-verified)
    // -----------------------------------------------------------------------

    /** POST /webhooks/paystack */
    public function webhookPaystack(Request $request): void
    {
        $this->handleWebhook('paystack', $request);
    }

    /** POST /webhooks/flutterwave */
    public function webhookFlutterwave(Request $request): void
    {
        $this->handleWebhook('flutterwave', $request);
    }

    // -----------------------------------------------------------------------

    private function handleWebhook(string $gateway, Request $request): void
    {
        $payload = $request->jsonBody();
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        try {
            $this->payments->processWebhook($gateway, $headers, $payload);
            Response::json(['received' => true]);
        } catch (HttpException $e) {
            // An invalid signature is a hard 4xx so the gateway stops retrying.
            Response::json(['received' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /** Accept an ?order= id, else fall back to the pending order in session. */
    private function resolveOrderId(Request $request): int
    {
        $orderId = $request->queryInt('order', 0);

        if ($orderId === 0) {
            $orderId = (int) Session::get('pending_order_id', 0);
        }
        if ($orderId === 0) {
            throw HttpException::notFound('No order was specified.');
        }
        return $orderId;
    }

    /**
     * Load an order, refusing if it belongs to somebody else.
     *
     * @return array<string,mixed>
     */
    private function ownedOrder(int $orderId): array
    {
        $order = Database::instance()->first(
            'SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1',
            ['id' => $orderId, 'customer_id' => Auth::id()]
        );

        if ($order === null) {
            \App\Logger::security('Customer attempted to act on an order they do not own', [
                'order_id' => $orderId,
                'user_id'  => Auth::id(),
            ]);
            throw HttpException::notFound('We could not find that order.');
        }
        return $order;
    }
}
