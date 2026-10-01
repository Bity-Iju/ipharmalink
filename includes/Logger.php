<?php

/**
 * iPharmaLink :: Filesystem logger
 * ---------------------------------------------------------------------------
 * Writes to storage/logs/iPharmaLink-YYYY-MM-DD.log. Failures to write are
 * swallowed — logging must never take the site down.
 */

declare(strict_types=1);

namespace App;

final class Logger
{
    private static ?string $channel = null;

    public static function path(): string
    {
        if (self::$channel === null) {
            $dir = APP_ROOT . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$channel = $dir . '/iPharmaLink-' . date('Y-m-d') . '.log';
        }
        return self::$channel;
    }

    /** @param array<string,mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if (PHP_SAPI === 'cli') {
            return;                       // keep CLI output clean
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::write('security', $message, $context);
    }
}
