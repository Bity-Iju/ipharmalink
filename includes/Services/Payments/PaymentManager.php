<?php

/**
 * iPharmaLink :: Payment gateway factory + orchestration
 * ---------------------------------------------------------------------------
 * Owns the payment lifecycle for an order:
 *   createPayment()  -> pending record + redirect URL (or instructions)
 *   confirmPayment() -> server-side verification, then mark the order paid
 *   processWebhook() -> signature validation, then the same confirmation path
 *   refundPayment()
 *
 * The browser return URL (/payment/success) never confirms anything by
 * itself; it only re-checks status. That is the single most important rule
 * in this file.
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\Database;
use App\HttpException;
use App\Logger;
use App\Request;
use App\Services\OrderService;
use App\Setting;

final class PaymentManager
{
    private Database $db;
    private OrderService $orders;

    /** code => implementing class */
    private const GATEWAYS = [
        'paystack'        => PaystackGateway::class,
        'flutterwave'     => FlutterwaveGateway::class,
        'bank_transfer'   => OfflineGateway::class,
        'cash_on_delivery' => OfflineGateway::class,
    ];

    public function __construct(?Database $db = null)
    {
        $this->db     = $db ?? Database::instance();
        $this->orders = new OrderService($this->db);
    }

    // -----------------------------------------------------------------------
    //  Factory
    // -----------------------------------------------------------------------

    public function gateway(string $code): PaymentGateway
    {
        $class = self::GATEWAYS[$code] ?? null;
        if ($class === null) {
            throw HttpException::badRequest('That payment method is not available.');
        }
        if ($class === OfflineGateway::class) {
            return new OfflineGateway($code);
        }
        return new $class();
    }

    /** @return list<array<string,mixed>> gateways the admin has enabled */
    public function availableGateways(): array
    {
        return $this->db->all(
            'SELECT code, name, is_sandbox, sort_order FROM payment_gateways
             WHERE is_enabled = 1 ORDER BY sort_order ASC, name ASC'
        );
    }

    public function isEnabled(string $code): bool
    {
        return $this->db->value('SELECT is_enabled FROM payment_gateways WHERE code = ?', ['code' => $code]) === 1
            || $this->db->value('SELECT is_enabled FROM payment_gateways WHERE code = ?', ['code' => $code]) === '1';
    }

    // -----------------------------------------------------------------------
    //  Start a payment
    // -----------------------------------------------------------------------

    /**
     * @return array{payment_id:int, redirect_url:string, gateway:string}
     */
    public function createPayment(int $orderId, int $customerId, string $gatewayCode): array
    {
        $order = $this->db->first(
            'SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1',
            ['id' => $orderId, 'customer_id' => $customerId]
        );
        if ($order === null) {
            throw HttpException::notFound('Order not found.');
        }
        if ($order['payment_status'] === 'successful') {
            throw HttpException::badRequest('This order has already been paid for.');
        }
        if ($order['status'] === OrderService::STATUS_CANCELLED) {
            throw HttpException::badRequest('This order was cancelled.');
        }

        $gateway = $this->gateway($gatewayCode);
        $amount  = (float) $order['total'];
        $reference = 'IPL-' . $orderId . '-' . strtolower(bin2hex(random_bytes(6)));

        $paymentId = $this->db->insert('payments', [
            'order_id'     => $orderId,
            'customer_id'  => $customerId,
            'gateway_code' => $gatewayCode,
            'amount'       => $amount,
            'currency'     => (string) $order['currency'],
            'status'       => 'pending',
            'reference'    => $reference,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->update('orders', ['payment_status' => 'processing'], 'id = ?', ['id' => $orderId]);

        $callbackUrl = Setting::getString('app.url', 'http://localhost:8000') . '/payment/return?reference=' . urlencode($reference);
        $redirectUrl = $gateway->initialize($order, $amount, $callbackUrl, $reference);

        $this->db->update('payments', ['status' => 'processing'], 'id = ?', ['id' => $paymentId]);

        return ['payment_id' => $paymentId, 'redirect_url' => $redirectUrl, 'gateway' => $gatewayCode];
    }

    // -----------------------------------------------------------------------
    //  Confirm — the only path to "paid"
    // -----------------------------------------------------------------------

    /**
     * Verify a payment server-to-server and, if genuine, mark the order paid.
     *
     * @return array{paid:bool, message:string}
     */
    public function confirmPayment(string $reference, int $expectedOrderId = 0): array
    {
        $payment = $this->db->first(
            'SELECT * FROM payments WHERE reference = ? LIMIT 1',
            ['reference' => $reference]
        );
        if ($payment === null) {
            Logger::warning('Confirmation for an unknown payment reference', ['reference' => $reference]);
            return ['paid' => false, 'message' => 'We could not find that payment. Please contact support.'];
        }
        if ($expectedOrderId > 0 && (int) $payment['order_id'] !== $expectedOrderId) {
            Logger::security('Payment reference / order mismatch', [
                'reference' => $reference,
                'payment_order' => $payment['order_id'],
                'expected' => $expectedOrderId,
            ]);
            return ['paid' => false, 'message' => 'This payment does not match the order.'];
        }
        if ($payment['status'] === 'successful') {
            return ['paid' => true, 'message' => 'Payment already confirmed.'];
        }

        $gateway = $this->gateway((string) $payment['gateway_code']);
        $result  = $gateway->verify($reference, (float) $payment['amount']);

        $this->db->insert('payment_transactions', [
            'payment_id'   => (int) $payment['id'],
            'gateway_code' => (string) $payment['gateway_code'],
            'event'        => 'verification',
            'gateway_ref'  => $result['reference'],
            'amount'       => $result['amount'],
            'status'       => $result['success'] ? 'successful' : 'failed',
            'payload'      => json_encode($result['raw'], JSON_UNESCAPED_SLASHES),
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        if (!$result['success']) {
            $this->db->update('payments', [
                'status'           => 'failed',
                'gateway_response' => json_encode($result['raw'], JSON_UNESCAPED_SLASHES),
            ], 'id = ?', ['id' => $payment['id']]);

            $this->db->update('orders', ['payment_status' => 'failed'], 'id = ?', ['id' => $payment['order_id']]);

            (new \App\Services\NotificationService())
                ->to(
                    (int) $payment['customer_id'],
                    'payment.failed',
                    'Payment could not be verified',
                    'We were unable to confirm your payment. Please try again or choose another method.',
                    '/account/orders/' . (int) $payment['order_id']
                )
                ->send();

            return ['paid' => false, 'message' => $result['message']];
        }

        $this->orders->markPaid((int) $payment['order_id'], (string) $payment['gateway_code'], $result['reference']);

        return ['paid' => true, 'message' => 'Payment confirmed. Thank you for your order.'];
    }

    /**
     * Handle an inbound webhook.
     *
     * @param array<string,string> $headers
     */
    public function processWebhook(string $gatewayCode, array $headers, array $payload): array
    {
        $gateway = $this->gateway($gatewayCode);
        $result  = $gateway->handleWebhook($headers, $payload);

        if (!$result['valid']) {
            $this->db->insert('payment_transactions', [
                'gateway_code' => $gatewayCode,
                'event'        => 'webhook_rejected',
                'amount'       => $result['amount'],
                'status'       => 'invalid_signature',
                'payload'      => json_encode($result['raw'], JSON_UNESCAPED_SLASHES),
                'ip_address'   => (new Request())->ip(),
            ]);
            Logger::security('Webhook rejected: ' . $result['message'], ['gateway' => $gatewayCode]);
            throw HttpException::forbidden('Invalid webhook signature.');
        }

        if ($result['status'] === 'successful' && $result['reference'] !== '') {
            $this->confirmPayment($result['reference']);
        } elseif ($result['status'] === 'failed') {
            $payment = $this->db->first(
                'SELECT id, order_id, customer_id FROM payments WHERE reference = ? LIMIT 1',
                ['reference' => $result['reference']]
            );
            if ($payment !== null) {
                $this->db->update('payments', ['status' => 'failed'], 'id = ?', ['id' => $payment['id']]);
                $this->db->update('orders', ['payment_status' => 'failed'], 'id = ?', ['id' => $payment['order_id']]);
            }
        }

        $this->db->insert('payment_transactions', [
            'gateway_code' => $gatewayCode,
            'event'        => 'webhook',
            'gateway_ref'  => $result['reference'],
            'amount'       => $result['amount'],
            'status'       => $result['status'],
            'payload'      => json_encode($result['raw'], JSON_UNESCAPED_SLASHES),
            'ip_address'   => (new Request())->ip(),
        ]);

        return $result;
    }

    // -----------------------------------------------------------------------
    //  Status polling (safe — read only)
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function paymentForOrder(int $orderId): ?array
    {
        return $this->db->first(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            ['order_id' => $orderId]
        );
    }

    public function orderPaymentStatus(int $orderId): string
    {
        return (string) $this->db->value('SELECT payment_status FROM orders WHERE id = ?', ['id' => $orderId]);
    }

    // -----------------------------------------------------------------------
    //  Refunds (admin initiated)
    // -----------------------------------------------------------------------

    public function refund(int $orderId, float $amount, string $reason, int $adminId): int
    {
        $order   = $this->db->first('SELECT * FROM orders WHERE id = ? LIMIT 1', ['id' => $orderId]);
        if ($order === null) {
            throw HttpException::notFound('Order not found.');
        }
        if ($order['payment_status'] !== 'successful') {
            throw HttpException::badRequest('Only a paid order can be refunded.');
        }
        if ($amount <= 0 || $amount > (float) $order['total']) {
            throw HttpException::badRequest('The refund amount is invalid.');
        }

        $payment = $this->paymentForOrder($orderId);

        $refundId = $this->db->insert('refunds', [
            'order_id'     => $orderId,
            'payment_id'   => $payment['id'] ?? null,
            'amount'       => $amount,
            'reason'       => $reason,
            'status'       => 'pending',
            'processed_by' => $adminId,
        ]);

        $fullRefund = $amount >= (float) $order['total'];
        $this->db->update('orders', [
            'payment_status' => $fullRefund ? 'refunded' : 'successful',
            'status'         => $fullRefund ? OrderService::STATUS_REFUNDED : $order['status'],
        ], 'id = ?', ['id' => $orderId]);

        if ($fullRefund) {
            $this->db->update('pharmacy_orders', ['status' => OrderService::STATUS_REFUNDED], 'order_id = ?', ['order_id' => $orderId]);
        }

        (new \App\Services\AuditService())->log(
            'payment.refund_requested',
            'order',
            $orderId,
            sprintf('Refund of ₦%s requested: %s', number_format($amount, 2), $reason)
        );

        return $refundId;
    }
}
