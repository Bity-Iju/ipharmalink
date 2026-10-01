<?php

/**
 * iPharmaLink :: Flutterwave gateway
 * ---------------------------------------------------------------------------
 * Same contract as Paystack. Credentials are read from the admin panel with
 * a .env fallback; sandbox is the default.
 *
 * Docs: https://docs.flutterwave.com/docs/payments/standard
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\Config;
use App\Database;
use App\Logger;

final class FlutterwaveGateway implements PaymentGateway
{
    private string $secretKey;
    private bool   $sandbox;
    private string $baseUrl;

    public function __construct()
    {
        $credentials = $this->credentials();
        $this->secretKey = (string) ($credentials['secret_key'] ?? Config::str('payments.flutterwave_secret_key'));
        $this->sandbox   = (bool) ($credentials['sandbox'] ?? true);
        $this->baseUrl   = $this->sandbox ? 'https://api.sandbox.flutterwave.com' : 'https://api.flutterwave.com';
    }

    public function code(): string
    {
        return 'flutterwave';
    }

    public function label(): string
    {
        return 'Flutterwave';
    }

    public function initialize(array $order, float $amount, string $callbackUrl, ?string $reference = null): string
    {
        $reference ??= 'IPL-' . $order['id'] . '-' . strtolower(bin2hex(random_bytes(6)));
        $email       = (string) Database::instance()->value(
            'SELECT email FROM users WHERE id = ?',
            ['id' => (int) $order['customer_id']]
        );

        $response = $this->request('POST', '/v3/payments', [
            'tx_ref'      => $reference,
            'amount'      => (float) $amount,
            'currency'    => 'NGN',
            'redirect_url' => $callbackUrl,
            'customer'    => [
                'email' => $email,
            ],
            'customizations' => [
                'title' => 'iPharmaLink Order ' . $order['order_number'],
            ],
            'meta' => [
                'order_id'     => (int) $order['id'],
                'order_number' => (string) $order['order_number'],
            ],
        ]);

        $link = $response['data']['link'] ?? null;
        if (empty($link)) {
            Logger::error('Flutterwave initialise failed', ['response' => $this->mask($response)]);
            throw new \RuntimeException('Could not start the payment. Please try again.');
        }

        $this->recordTransaction((int) $order['id'], 'initialized', $reference, $amount, $reference, $response);
        return (string) $link;
    }

    public function verify(string $reference, float $expectedAmount): array
    {
        $response = $this->request('GET', '/v3/transactions/' . rawurlencode($reference) . '/verify');

        $status     = (string) ($response['data']['status'] ?? '');
        $gatewayRef = (string) ($response['data']['id'] ?? '');
        $amount     = (float) ($response['data']['amount'] ?? 0);
        $currency   = (string) ($response['data']['currency'] ?? 'NGN');

        $success = $status === 'successful'
            && $gatewayRef === $reference
            && $currency === 'NGN'
            && abs($amount - $expectedAmount) < 0.01;

        $this->recordTransaction(null, 'verify', $reference, $amount, $gatewayRef, $response);

        return [
            'success'   => $success,
            'reference' => $gatewayRef,
            'amount'    => $amount,
            'raw'       => $response,
            'message'   => $success ? 'Payment verified.' : sprintf('Gateway reported status "%s".', $status !== '' ? $status : 'unknown'),
        ];
    }

    public function handleWebhook(array $headers, array $payload): array
    {
        $body      = (string) ($payload['_raw_body'] ?? '');
        $signature = $this->header($headers, 'x-flw-signature');
        $secretHash = (string) ($this->credentials()['hash_secret'] ?? Config::str('payments.flutterwave_hash_secret'));

        // Flutterwave: HMAC-SHA256 of the raw body, keyed by the hash secret.
        $expected = $secretHash !== ''
            ? hash_hmac('sha256', $body, $secretHash)
            : '';
        $valid = $expected !== '' && $signature !== '' && hash_equals($expected, $signature);

        $data   = $payload['data'] ?? [];
        $status = (string) ($data['status'] ?? '');

        if (!$valid) {
            Logger::security('Rejected Flutterwave webhook with invalid signature');
        }

        return [
            'valid'     => $valid,
            'reference' => (string) ($data['id'] ?? ''),
            'amount'    => (float) ($data['amount'] ?? 0),
            'status'    => $status === 'successful' ? 'successful' : ($status === 'failed' ? 'failed' : 'pending'),
            'raw'       => $this->mask($payload),
            'message'   => $valid ? 'Webhook verified.' : 'Invalid webhook signature.',
        ];
    }

    // -----------------------------------------------------------------------

    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('Flutterwave cURL failure', ['error' => $error, 'path' => $path]);
            return ['status' => false, 'message' => 'Could not reach the payment provider.'];
        }
        $decoded = json_decode((string) $body, true);
        if ($status >= 400) {
            Logger::error('Flutterwave API error', ['http' => $status, 'body' => $this->mask(is_array($decoded) ? $decoded : [])]);
        }
        return is_array($decoded) ? $decoded : ['status' => false, 'message' => 'Unreadable gateway response.'];
    }

    /** @return array<string,mixed> */
    private function credentials(): array
    {
        $row = Database::instance()->first(
            'SELECT credentials FROM payment_gateways WHERE code = ? LIMIT 1',
            ['code' => 'flutterwave']
        );
        if ($row === null || $row['credentials'] === null) {
            return [];
        }
        $decoded = json_decode((string) $row['credentials'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function recordTransaction(?int $paymentId, string $event, string $reference, float $amount, string $gatewayRef, array $payload): void
    {
        try {
            Database::instance()->insert('payment_transactions', [
                'payment_id'   => $paymentId,
                'gateway_code' => $this->code(),
                'event'        => $event,
                'gateway_ref'  => $gatewayRef,
                'amount'       => $amount,
                'status'       => (string) ($payload['data']['status'] ?? 'unknown'),
                'payload'      => json_encode($this->mask($payload), JSON_UNESCAPED_SLASHES),
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Could not record Flutterwave transaction', ['error' => $e->getMessage()]);
        }
    }

    private function mask(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->mask($value);
            } elseif (in_array(strtolower((string) $key), ['authorization', 'access_code', 'secret', 'api_key', 'hash_secret'], true)) {
                $payload[$key] = '[redacted]';
            }
        }
        return $payload;
    }

    /** @param array<string,string> $headers */
    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return (string) $value;
            }
        }
        return '';
    }
}
