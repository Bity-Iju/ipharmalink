<?php
/**
 * iPharmaLink :: Environment (.env) loader
 * ---------------------------------------------------------------------------
 * Reads a .env file (KEY=VALUE), exposes values through a small typed accessor
 * with defaults declared in config/config.php.
 */

declare(strict_types=1);

final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            // Fall back to real environment variables (container / CI friendly).
            self::$vars = [];
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // strip matching surrounding quotes
            if (strlen($value) > 1
                && (($value[0] === '"' && str_ends_with($value, '"'))
                 || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $sys = getenv($key);
        if ($sys !== false && $sys !== '') {
            return $sys;
        }
        return self::$vars[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
