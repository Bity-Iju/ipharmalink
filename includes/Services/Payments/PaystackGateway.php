<?php

/**
 * iPharmaLink :: Paystack gateway
 * ---------------------------------------------------------------------------
 * Credentials come from payment_gateways.credentials (JSON, editable in the
 * admin panel) with a fallback to the .env keys. Sandbox mode is the default
 * so a misconfigured live key cannot take real money during development.
 *
 * Docs: https://paystack.com/docs/payments/initialize/
 */

declare(strict_types=1);

namespace App\Services\Payments;

use App\Config;
use App\Database;
use App\Logger;

final class PaystackGateway implements PaymentGateway
{
    private string $secretKey;
    private bool   $sandbox;
    private string $baseUrl;

    public function __construct()
    {
        $credentials = $this->credentials();
        $this->secretKey = (string) ($credentials['secret_key'] ?? Config::str('payments.paystack_secret_key'));
        $this->sandbox   = (bool) ($credentials['sandbox'] ?? true);
        $this->baseUrl   = $this->sandbox ? 'https://api.sandbox.paystack.co' : 'https://api.paystack.co';
    }

    public function code(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack';
    }

    public function initialize(array $order, float $amount, string $callbackUrl, ?string $reference = null): string
    {
        $reference ??= $this->makeReference($order);
        $email       = (string) Database::instance()->value(
            'SELECT email FROM users WHERE id = ?',
            ['id' => (int) $order['customer_id']]
        );

        $payload = [
            'email'        => $email,
            'amount'       => (int) round($amount * 100),   // kobo
            'currency'     => 'NGN',
            'reference'    => $reference,
            'callback_url' => $callbackUrl,
            'metadata'     => [
                'order_id'      => (int) $order['id'],
                'order_number'  => (string) $order['order_number'],
                'customer_id'   => (int) $order['customer_id'],
            ],
        ];

        $response = $this->request('POST', '/transaction/initialize', $payload);

        if (($response['status'] ?? false) !== true || empty($response['data']['authorization_url'])) {
            Logger::error('Paystack initialise failed', ['response' => $response]);
            throw new \RuntimeException('Could not start the payment. Please try again.');
        }

        $this->recordTransaction(
            (int) $order['id'],
            'initialized',
            $reference,
            $amount,
            $response['data']['reference'] ?? $reference,
            $response
        );

        return (string) $response['data']['authorization_url'];
    }

    public function verify(string $reference, float $expectedAmount): array
    {
        $response = $this->request('GET', '/transaction/verify/' . rawurlencode($reference));

        $status  = (string) ($response['data']['status'] ?? '');
        $amount  = (float) (($response['data']['amount'] ?? 0) / 100);
        $success = $status === 'success'
            && abs($amount - $expectedAmount) < 0.01      // amount must match exactly
            && ($response['data']['currency'] ?? 'NGN') === 'NGN';

        $this->recordTransaction(
            null,
            'verify',
            $reference,
            $amount,
            $reference,
            $response,
            $response['data'] !== null ? (int) $response['data']['id'] : null
        );

        return [
            'success'   => $success,
            'reference' => (string) ($response['data']['reference'] ?? $reference),
            'amount'    => $amount,
            'raw'       => $response,
            'message'   => $success
                ? 'Payment verified.'
                : sprintf('Gateway reported status "%s".', $status !== '' ? $status : 'unknown'),
        ];
    }

    public function handleWebhook(array $headers, array $payload): array
    {
        // Paystack signs the raw body with the webhook secret.
        $body      = $payload['_raw_body'] ?? '';
        $signature = $this->header($headers, 'x-paystack-signature');
        $secret    = (string) ($this->credentials()['webhook_secret']
            ?? Config::str('payments.paystack_webhook_secret'));

        $valid = $secret !== '' && $signature !== '' && hash_equals(
            hash_hmac('sha512', (string) $body, $secret),
            $signature
        );

        $data    = $payload['data'] ?? [];
        $amount  = (float) (($data['amount'] ?? 0) / 100);
        $status  = (string) ($data['status'] ?? '');

        if (!$valid) {
            Logger::security('Rejected Paystack webhook with invalid signature', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        }

        return [
            'valid'     => $valid,
            'reference' => (string) ($data['reference'] ?? ''),
            'amount'    => $amount,
            'status'    => $status === 'success' ? 'successful' : ($status === 'failed' ? 'failed' : 'pending'),
            'raw'       => $this->mask($payload),
            'message'   => $valid ? 'Webhook verified.' : 'Invalid webhook signature.',
        ];
    }

    // -----------------------------------------------------------------------

    private function request(string $method, string $path, array $payload = []): array
    {
        $url     = $this->baseUrl . $path;
        $ch      = curl_init($url);
        $headers = ['Authorization: Bearer ' . $this->secretKey, 'Accept: application/json'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('Paystack cURL failure', ['error' => $error, 'path' => $path]);
            return ['status' => false, 'message' => 'Could not reach the payment provider.'];
        }
        $decoded = json_decode((string) $body, true);

        if ($status >= 400) {
            Logger::error('Paystack API error', ['http' => $status, 'body' => $decoded]);
        }
        return is_array($decoded) ? $decoded : ['status' => false, 'message' => 'Unreadable gateway response.'];
    }

    /** @return array<string,mixed> */
    private function credentials(): array
    {
        $row = Database::instance()->first(
            'SELECT credentials FROM payment_gateways WHERE code = ? LIMIT 1',
            ['code' => 'paystack']
        );
        if ($row === null || $row['credentials'] === null) {
            return [];
        }
        $decoded = json_decode((string) $row['credentials'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function makeReference(array $order): string
    {
        return 'IPL-' . $order['id'] . '-' . strtolower(bin2hex(random_bytes(6)));
    }

    private function recordTransaction(?int $paymentId, string $event, string $reference, float $amount, string $gatewayRef, array $payload, ?int $gwId = null): void
    {
        try {
            Database::instance()->insert('payment_transactions', [
                'payment_id'   => $paymentId,
                'gateway_code' => $this->code(),
                'event'        => $event,
                'gateway_ref'  => $gatewayRef !== '' ? $gatewayRef : ($gwId !== null ? (string) $gwId : null),
                'amount'       => $amount,
                'status'       => (string) ($payload['data']['status'] ?? 'unknown'),
                'payload'      => json_encode($this->mask($payload), JSON_UNESCAPED_SLASHES),
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Could not record payment transaction', ['error' => $e->getMessage()]);
        }
    }

    /** Strip secrets and PII before persisting a gateway payload. */
    private function mask(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->mask($value);
            } elseif (in_array(strtolower((string) $key), ['authorization', 'access_code', 'secret', 'api_key'], true)) {
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
