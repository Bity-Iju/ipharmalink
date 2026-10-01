<?php

/**
 * iPharmaLink :: Platform settings service
 * ---------------------------------------------------------------------------
 * Settings live in platform_settings (admin editable) with a per-request
 * cache and a code fallback. Reads are pure; writes are admin-only and are
 * audited.
 */

declare(strict_types=1);

namespace App;

final class Setting
{
    /** @var array<string,string|null> */
    private static array $cache = [];
    private static bool $loaded = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return $value === null ? $default : (string) $value;
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (float) $value;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /** @param array<string,mixed> $settings key => value */
    public static function setMany(array $settings): void
    {
        Database::instance()->transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $existing = Database::instance()->first(
                    'SELECT id FROM platform_settings WHERE `key_name` = ? LIMIT 1',
                    ['key_name' => $key]
                );
                if ($existing) {
                    Database::instance()->update(
                        'platform_settings',
                        ['value' => (string) $value],
                        'id = ?',
                        ['id' => $existing['id']]
                    );
                } else {
                    $group = str_contains($key, '.') ? explode('.', $key)[0] : 'general';
                    Database::instance()->insert('platform_settings', [
                        'group_name' => $group,
                        'key_name'   => $key,
                        'value'      => (string) $value,
                    ]);
                }
            }
        });
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache  = [];
        self::$loaded = false;
    }

    public static function currencySymbol(): string
    {
        $symbol = self::getString('currency.currency_symbol', '₦');
        return html_entity_decode($symbol, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Per-pharmacy settings with fallback to platform defaults. Isolates the
     * delivery/pricing rules a pharmacy owner can change from the platform's.
     */
    public static function forPharmacy(int $pharmacyId, string $key, mixed $default = null): mixed
    {
        static $cache = [];
        $cacheKey = $pharmacyId . ':' . $key;

        if (!array_key_exists($cacheKey, $cache)) {
            $value = Database::instance()->value(
                'SELECT value FROM pharmacy_settings WHERE pharmacy_id = ? AND key_name = ? LIMIT 1',
                ['pharmacy_id' => $pharmacyId, 'key_name' => $key]
            );
            $cache[$cacheKey] = $value;
        }
        return $cache[$cacheKey] ?? $default;
    }

    public static function setForPharmacy(int $pharmacyId, string $key, string $value): void
    {
        $existing = Database::instance()->first(
            'SELECT id FROM pharmacy_settings WHERE pharmacy_id = ? AND key_name = ? LIMIT 1',
            ['pharmacy_id' => $pharmacyId, 'key_name' => $key]
        );
        if ($existing) {
            Database::instance()->update(
                'pharmacy_settings',
                ['value' => $value],
                'id = ?',
                ['id' => $existing['id']]
            );
        } else {
            Database::instance()->insert('pharmacy_settings', [
                'pharmacy_id' => $pharmacyId,
                'key_name'    => $key,
                'value'       => $value,
            ]);
        }
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        try {
            $rows = Database::instance()->all('SELECT key_name, value FROM platform_settings');
            foreach ($rows as $row) {
                self::$cache[(string) $row['key_name']] = (string) $row['value'];
            }
        } catch (\Throwable) {
            // Settings table missing (fresh install) — fall back to code defaults.
            Logger::warning('Could not load platform settings; using defaults.');
        }
    }
}
