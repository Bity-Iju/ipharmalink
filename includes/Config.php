<?php

/**
 * iPharmaLink :: Application configuration values
 */

declare(strict_types=1);

namespace App;

// Env is a global class (config/Env.php) imported explicitly so it does not
// resolve as App\Env from inside this namespace.
use Env;

final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];
    private static bool $frozen = false;

    public static function set(array $items): void
    {
        self::$items = $items;
        self::$frozen = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key);
        return ($v === null || $v === '') ? $default : (float) $v;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::get($key);
        return $v === null ? $default : (string) $v;
    }

    /**
     * Read a variable straight from the environment, normalising booleans.
     * Used by config/config.php before App\Config exists.
     */
    public static function env(string $key, string $default = ''): string
    {
        $value = Env::get($key, $default);
        if ($value === null) {
            return $default;
        }
        $lower = strtolower($value);
        if ($lower === 'true') {
            return 'true';
        }
        if ($lower === 'false') {
            return 'false';
        }
        return $value;
    }

    /** Test seam: allow overriding in isolated test runs. */
    public static function setValue(string $key, mixed $value): void
    {
        if (self::$frozen) {
            // Frozen in production; still allow explicit override for tooling.
            self::$items[$key] = $value;
        }
    }

    public static function all(): array
    {
        return self::$items;
    }
}
