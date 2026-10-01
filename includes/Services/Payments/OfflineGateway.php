<?php

/**
 * iPharmaLink :: Offline payment gateway
 * ---------------------------------------------------------------------------
 * Bank transfer and cash on delivery. There is nothing to verify with a
 * third party, so verification is deferred: the order stays 'pending' until
 * an admin confirms the transfer landed. This class deliberately returns
 * success = false from verify() so no code path can mark such an order paid
 * without a human decision.
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\Database;
use App\Logger;
use App\Setting;

final class OfflineGateway implements PaymentGateway
{
    public function __construct(private string $variant = 'bank_transfer') {}

    public function code(): string
    {
        return $this->variant;
    }

    public function label(): string
    {
        return $this->variant === 'cash_on_delivery' ? 'Cash on Delivery' : 'Bank Transfer';
    }

    public function initialize(array $order, float $amount, string $callbackUrl, ?string $reference = null): string
    {
        $reference ??= 'IPL-' . $order['id'] . '-' . strtolower(bin2hex(random_bytes(6)));

        Database::instance()->insert('payment_transactions', [
            'payment_id'   => null,
            'gateway_code' => $this->code(),
            'event'        => 'instructions_issued',
            'gateway_ref'  => $reference,
            'amount'       => $amount,
            'status'       => 'pending',
            'payload'      => json_encode(['order_number' => $order['order_number']], JSON_UNESCAPED_SLASHES),
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        // The controller renders the transfer instructions and links to
        // /payment/instructions?order=… — nothing to redirect to.
        return Setting::getString('app.url') . '/payment/instructions?order=' . (int) $order['id'] . '&ref=' . urlencode($reference);
    }

    public function verify(string $reference, float $expectedAmount): array
    {
        return [
            'success'   => false,
            'reference' => $reference,
            'amount'    => $expectedAmount,
            'raw'       => [],
            'message'   => 'Offline payments require confirmation by our team before the order is marked paid.',
        ];
    }

    public function handleWebhook(array $headers, array $payload): array
    {
        Logger::warning('Webhook received for offline gateway — ignored', ['gateway' => $this->code()]);
        return [
            'valid'     => false,
            'reference' => '',
            'amount'    => 0.0,
            'status'    => 'pending',
            'raw'       => [],
            'message'   => 'Offline gateways do not accept webhooks.',
        ];
    }

    /** Bank details shown on the instructions page. */
    public function instructions(array $pharmacy): array
    {
        return [
            'bank_name'        => $pharmacy['bank_name'] ?? 'Not configured',
            'account_name'     => $pharmacy['bank_account_name'] ?? '',
            'account_number'   => $pharmacy['bank_account_number'] ?? '',
            'support_email'    => Setting::getString('general.support_email', ''),
            'support_phone'    => Setting::getString('general.support_phone', ''),
        ];
    }
}
