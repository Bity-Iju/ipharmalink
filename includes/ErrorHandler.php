<?php

/**
 * iPharmaLink :: Error handling
 * ---------------------------------------------------------------------------
 * Converts PHP warnings/notices and uncaught exceptions into safe responses.
 * In debug mode the detail is rendered; in production only a friendly page
 * is shown and details go to the log. SQL and stack traces are never exposed.
 */

declare(strict_types=1);

namespace App;

use Throwable;

final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;   // suppressed with @
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::handle($e);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::render(500, 'A server error occurred.', $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
            }
        });
    }

    public static function handle(Throwable $e): void
    {
        $isClientError = $e instanceof HttpException;
        $status        = $isClientError ? $e->getStatusCode() : 500;

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        self::render($status, $isClientError ? $e->getMessage() : 'Something went wrong on our side.', $e->getMessage());
    }

    /** Terminate immediately with a safe message (used for bootstrap failures). */
    public static function fatal(string $safeMessage): void
    {
        self::render(500, $safeMessage, $safeMessage);
        exit(1);
    }

    public static function render(int $status, string $safeMessage, ?string $debugDetail = null): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[{$status}] {$safeMessage}\n");
            if (self::$debug && $debugDetail) {
                fwrite(STDERR, $debugDetail . "\n");
            }
            return;
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        $view = APP_ROOT . '/templates/errors/' . $status . '.php';
        if (is_file($view)) {
            $errorTitle  = Response::statusTitle($status);
            $errorDetail = self::$debug ? $debugDetail : null;
            require $view;
            return;
        }

        echo '<!doctype html><meta charset="utf-8"><title>' . htmlspecialchars((string) $status) . '</title>';
        echo '<div style="font-family:system-ui;padding:3rem;text-align:center">';
        echo '<h1>' . Response::statusTitle($status) . '</h1><p>' . htmlspecialchars($safeMessage) . '</p>';
        echo '<p><a href="/">Return to homepage</a></p></div>';
    }
}
