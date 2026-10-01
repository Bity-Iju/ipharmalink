<?php

declare(strict_types=1);

namespace IpharmaLink;

use PDO;
use RuntimeException;

final class Payments
{
    public static function create(PDO $db, int $payerId, array $input): array
    {
        $orderId = (int) ($input['wholesale_order_id'] ?? 0);
        $method = $input['method'] ?? 'bank_transfer';
        $amount = (float) ($input['amount'] ?? 0);

        if ($amount <= 0) throw new RuntimeException('Amount must be greater than zero.');
        if (!in_array($method, ['paystack', 'flutterwave', 'bank_transfer', 'wallet', 'credit', 'manual'], true)) {
            throw new RuntimeException('Invalid payment method.');
        }

        $order = $db->prepare('SELECT id, total FROM wholesale_orders WHERE id = ?');
        $order->execute([$orderId]);
        $o = $order->fetch();
        if (!$o || (float) $o['total'] !== $amount) throw new RuntimeException('Order not found or amount mismatch.');

        $ref = strtoupper('PAY-' . date('YmdHis') . '-' . random_int(1000, 9999));
        $stmt = $db->prepare(
            'INSERT INTO payments (reference, payer_id, wholesale_order_id, method, status, amount, gateway_reference) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ref, $payerId, $orderId, $method, 'pending', $amount, $input['gateway_reference'] ?? null]);
        $paymentId = (int) $db->lastInsertId();

        if ($method === 'paystack') {
            return self::initPaystack($ref, $amount, $payerId);
        } elseif ($method === 'flutterwave') {
            return self::initFlutterwave($ref, $amount, $payerId);
        } elseif ($method === 'bank_transfer') {
            return ['reference' => $ref, 'status' => 'pending', 'instructions' => 'Please transfer to: GTBank 0123456789 | Name: iPharmaLink Pharmacy'];
        }

        return ['reference' => $ref, 'status' => 'pending'];
    }

    private static function initPaystack(string $ref, float $amount, int $payerId): array
    {
        $key = getenv('PAYSTACK_SECRET') ?: 'pk_test_xxx';
        $user = $GLOBALS['db']->prepare('SELECT email FROM users WHERE id = ?');
        $user->execute([$payerId]);
        $email = $user->fetchColumn();

        $payload = json_encode(['email' => $email, 'amount' => (int) ($amount * 100), 'reference' => $ref]);
        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['status'] ?? false) {
            return ['reference' => $ref, 'redirect_url' => $response['data']['authorization_url'] ?? null];
        }
        throw new RuntimeException('Paystack initialization failed.');
    }

    private static function initFlutterwave(string $ref, float $amount, int $payerId): array
    {
        $key = getenv('FLUTTERWAVE_SECRET') ?: 'FLWSECK_TEST_xxx';
        $user = $GLOBALS['db']->prepare('SELECT email FROM users WHERE id = ?');
        $user->execute([$payerId]);
        $email = $user->fetchColumn();

        $payload = json_encode([
            'tx_ref' => $ref,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => 'http://localhost:8000/payment-callback',
            'customer' => ['email' => $email],
        ]);
        $ch = curl_init('https://api.flutterwave.com/v3/payments');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['status'] === 'success') {
            return ['reference' => $ref, 'redirect_url' => $response['data']['link'] ?? null];
        }
        throw new RuntimeException('Flutterwave initialization failed.');
    }

    public static function verifyAndMarkPaid(PDO $db, string $reference): array
    {
        $payment = $db->prepare('SELECT id, wholesale_order_id, method, amount FROM payments WHERE reference = ? LIMIT 1');
        $payment->execute([$reference]);
        $p = $payment->fetch();
        if (!$p) throw new RuntimeException('Payment not found.');

        if ($p['method'] === 'paystack') {
            $verified = self::verifyPaystack($reference);
        } elseif ($p['method'] === 'flutterwave') {
            $verified = self::verifyFlutterwave($reference);
        } else {
            $verified = true;
        }

        if ($verified) {
            $db->prepare('UPDATE payments SET status = "successful", paid_at = CURRENT_TIMESTAMP WHERE reference = ?')->execute([$reference]);
            $db->prepare('UPDATE wholesale_orders SET status = "accepted" WHERE id = ?')->execute([$p['wholesale_order_id']]);
        }

        return ['verified' => $verified, 'reference' => $reference];
    }

    private static function verifyPaystack(string $ref): bool
    {
        $key = getenv('PAYSTACK_SECRET') ?: 'pk_test_xxx';
        $ch = curl_init("https://api.paystack.co/transaction/verify/{$ref}");
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"], CURLOPT_RETURNTRANSFER => true]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return ($response['status'] ?? false) && ($response['data']['status'] === 'success');
    }

    private static function verifyFlutterwave(string $ref): bool
    {
        $key = getenv('FLUTTERWAVE_SECRET') ?: 'FLWSECK_TEST_xxx';
        $ch = curl_init("https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref={$ref}");
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"], CURLOPT_RETURNTRANSFER => true]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return ($response['status'] === 'success');
    }
}
