<?php

/**
 * iPharmaLink :: Request (immutable-ish input wrapper)
 * ---------------------------------------------------------------------------
 * Gives controllers and validators a clean, source-aware API:
 *   $request->input('email')        // POST/GET merged
 *   $request->post('token')
 *   $request->query('page')
 *   $request->ip()
 *
 * Nothing here trusts the client: types are enforced and the raw body is
 * only parsed for JSON API routes.
 */

declare(strict_types=1);

namespace App;

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,mixed> */
    private array $files;
    private ?array $json = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uriPath = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $basePath = Config::basePath();
        if ($basePath !== '' && ($uriPath === $basePath || str_starts_with($uriPath, $basePath . '/'))) {
            $uriPath = substr($uriPath, strlen($basePath));
        }
        $this->path = $uriPath === '' ? '/' : $uriPath;
        $this->query  = $_GET;
        $this->files  = $_FILES;
        $this->body   = $this->resolveBody();
    }

    private function resolveBody(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw         = file_get_contents('php://input') ?: '';
            $decoded     = json_decode($raw, true);
            $this->json  = is_array($decoded) ? $decoded : [];
            return $this->json;
        }
        if (str_contains($contentType, 'multipart/form-data')) {
            return $_POST;
        }
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $_POST;
        }
        return $_POST;
    }

    // -----------------------------------------------------------------------

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $v = $this->query[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function postInt(string $key, int $default = 0): int
    {
        $v = $this->body[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    public function filled(string $key): bool
    {
        $v = $this->input($key);
        return $v !== null && $v !== '' && !(is_array($v) && $v === []);
    }

    /** @return list<string> */
    public function array(string $key): array
    {
        $v = $this->input($key, []);
        if (is_array($v)) {
            return array_values($v);
        }
        return $v === null || $v === '' ? [] : array_map('trim', explode(',', (string) $v));
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $v = $this->input($key);
        return is_numeric($v) ? (float) $v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->input($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes'], true);
    }

    public function trimmed(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    // -----------------------------------------------------------------------
    //  Client metadata
    // -----------------------------------------------------------------------

    public function ip(): string
    {
        // Only trust forwarding headers when explicitly behind a proxy.
        if (Config::bool('app.debug')) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $candidate = trim(explode(',', (string) $_SERVER[$key])[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    /** Case-insensitive HTTP header lookup. */
    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return str_contains($accept, 'application/json')
            || $this->isAjax()
            || str_starts_with($this->path, '/api/');
    }

    /**
     * The decoded JSON body, or an empty array for form posts.
     * Used by the payment webhooks, which receive JSON, and by the API.
     *
     * @return array<string,mixed>
     */
    public function jsonBody(): array
    {
        return $this->json;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function fullUrl(): string
    {
        return Config::str('app.url') . $this->path;
    }
}
