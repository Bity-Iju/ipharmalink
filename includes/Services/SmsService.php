<?php

/**
 * iPharmaLink :: SMS service
 * ---------------------------------------------------------------------------
 * Provider-agnostic stub. The `log` driver records messages locally; swap in
 * Termii, BulkSMS, Arkesel or Africa's Talking by implementing send() for
 * that provider — no business code changes.
 */

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Logger;

final class SmsService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = Config::str('sms.driver', 'log');
    }

    public function send(string $phone, string $message): bool
    {
        $phone = $this->normalise($phone);
        if ($phone === null) {
            Logger::warning('Refusing SMS to an invalid number', ['phone' => $phone]);
            return false;
        }

        // Keep within a single GSM-7 segment where possible.
        if (strlen($message) > 160) {
            $message = mb_substr($message, 0, 157) . '...';
        }

        if ($this->driver === 'log') {
            $dir = APP_ROOT . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents(
                $dir . '/sms.log',
                sprintf("[%s] TO: %s | %s\n", date('Y-m-d H:i:s'), $phone, $message),
                FILE_APPEND | LOCK_EX
            );
            return true;
        }

        // ---- plug your provider here ---------------------------------------
        Logger::info('SMS dispatch not implemented for driver ' . $this->driver, ['phone' => $phone]);
        return false;
    }

    /** Normalise to E.164-ish; Nigerian local numbers become +234… */
    public function normalise(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '+234' . substr($digits, 1);
        }
        return preg_match('/^\+?[0-9]{10,15}$/', $digits) === 1 ? $digits : null;
    }
}
