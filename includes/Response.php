<?php

/**
 * iPharmaLink :: Response helper
 * ---------------------------------------------------------------------------
 * Redirect, JSON, and status-title utilities. Kept deliberately small so
 * controllers read cleanly:  return $this->json([...])  /  $response->redirect('/x')
 */

declare(strict_types=1);

namespace App;

final class Response
{
    public static function statusTitle(int $status): string
    {
        return [
            400 => 'Bad Request',
            401 => 'Unauthorised',
            403 => 'Access Denied',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            422 => 'Validation Failed',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ][$status] ?? 'Error';
    }

    /** 303 so the browser follows up with GET after a POST. */
    public static function redirect(string $url, int $status = 303): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
        exit;
    }

    public static function back(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $url = ($referer !== '' && self::isInternal($referer)) ? $referer : $fallback;
        self::redirect($url);
    }

    /** @param array<string,mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noContent(): void
    {
        if (!headers_sent()) {
            http_response_code(204);
        }
        exit;
    }

    public static function download(string $path, string $filename, string $mime = 'application/octet-stream'): void
    {
        if (!is_file($path)) {
            throw HttpException::notFound('The requested file no longer exists.');
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
        }
        readfile($path);
        exit;
    }

    /** Guard against open-redirect via Host / Referer headers. */
    public static function isInternal(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        $base = parse_url(Config::str('app.url'), PHP_URL_HOST);
        return $host !== null && $base !== null && strcasecmp($host, $base) === 0;
    }
}
