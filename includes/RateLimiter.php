<?php

/**
 * iPharmaLink :: Rate limiter
 * ---------------------------------------------------------------------------
 * Fixed-window counter kept in MySQL so it works across processes/load
 * balancers. Buckets are declared in config('security.rate_limits').
 *
 *   RateLimiter::hit('login');            // throws 429 when exceeded
 *   RateLimiter::tooManyAttempts('login');
 */

declare(strict_types=1);

namespace App;

final class RateLimiter
{
    public static function hit(string $action, ?string $identifier = null): void
    {
        if (self::exceeded($action, $identifier)) {
            throw HttpException::tooManyRequests(
                sprintf('Too many attempts. Please try again in %d minutes.', self::availableIn($action))
            );
        }
        self::increment($action, $identifier);
    }

    public static function exceeded(string $action, ?string $identifier = null): bool
    {
        [$max] = self::limits($action);
        if ($max <= 0) {
            return false;
        }
        $key     = self::key($action, $identifier);
        $current = Database::instance()->value(
            'SELECT hits FROM rate_limits WHERE bucket_key = ? AND expires_at > NOW() LIMIT 1',
            [$key]
        );
        return (int) $current >= $max;
    }

    public static function attempts(string $action, ?string $identifier = null): int
    {
        $key = self::key($action, $identifier);
        return (int) Database::instance()->value(
            'SELECT hits FROM rate_limits WHERE bucket_key = ? AND expires_at > NOW() LIMIT 1',
            [$key]
        );
    }

    public static function remaining(string $action, ?string $identifier = null): int
    {
        [$max] = self::limits($action);
        return max(0, $max - self::attempts($action, $identifier));
    }

    /** Clear a bucket — call this after a successful login / verification. */
    public static function clear(string $action, ?string $identifier = null): void
    {
        Database::instance()->delete(
            'rate_limits',
            'bucket_key = ?',
            [self::key($action, $identifier)]
        );
    }

    public static function availableIn(string $action, ?string $identifier = null): int
    {
        [, $minutes] = self::limits($action);
        return max(1, $minutes);
    }

    public static function retryAfter(string $action, ?string $identifier = null): int
    {
        $seconds = (int) Database::instance()->value(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM rate_limits WHERE bucket_key = ? LIMIT 1',
            [self::key($action, $identifier)]
        );
        return max(1, $seconds);
    }

    // -----------------------------------------------------------------------

    private static function increment(string $action, ?string $identifier): void
    {
        $db  = Database::instance();
        $key = self::key($action, $identifier);
        [, $minutes] = self::limits($action);

        $db->run(
            'INSERT INTO rate_limits (bucket_key, hits, window_started_at, expires_at)
             VALUES (:key, 1, NOW(), DATE_ADD(NOW(), INTERVAL :mins MINUTE))
             ON DUPLICATE KEY UPDATE
                hits = IF(expires_at <= NOW(), 1, hits + 1),
                window_started_at = IF(expires_at <= NOW(), NOW(), window_started_at),
                expires_at = IF(expires_at <= NOW(), DATE_ADD(NOW(), INTERVAL :mins2 MINUTE), expires_at)',
            ['key' => $key, 'mins' => $minutes, 'mins2' => $minutes]
        );
    }

    /** @return array{0:int,1:int}  [max hits, window minutes] */
    private static function limits(string $action): array
    {
        $limits = Config::get('security.rate_limits', []);
        $entry  = $limits[$action] ?? ['max' => 60, 'minutes' => 5];
        return [(int) $entry['max'], (int) $entry['minutes']];
    }

    private static function key(string $action, ?string $identifier): string
    {
        $identifier ??= (new Request())->ip();
        return substr($action . ':' . $identifier, 0, 160);
    }
}
