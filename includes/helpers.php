<?php

/**
 * iPharmaLink :: Helpers
 * ---------------------------------------------------------------------------
 * Global utility functions, namespaced with `ipl_` to avoid collisions with
 * any CMS or library the project later adopts.
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escape for HTML text/attribute context. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    function e_attr(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('money')) {
    /**
     * Format a Naira amount. ₦1,500.00
     * Values are treated as kobo-free decimals; rounding happens here only,
     * never during calculation.
     */
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $value = round((float) ($amount ?? 0), 2);
        $formatted = number_format($value, 2, '.', ',');
        return $withSymbol ? '₦' . $formatted : $formatted;
    }
}

if (!function_exists('money_compact')) {
    /** ₦24.9k / ₦1.2m — for dashboard tiles. */
    function money_compact(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        return match (true) {
            abs($value) >= 1_000_000 => '₦' . rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.') . 'm',
            abs($value) >= 1_000     => '₦' . rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.') . 'k',
            default                  => '₦' . number_format($value, 0),
        };
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $text, int $limit = 120): string
    {
        $text = trim(strip_tags((string) $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit)) . '…';
    }
}

if (!function_exists('slugify_text')) {
    function slugify_text(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['&', "'"], ['and', ''], $text);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
        return trim($text, '-') ?: bin2hex(random_bytes(4));
    }
}

if (!function_exists('asset')) {
    /** Cache-busted asset URL. */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = APP_ROOT . $path;
        $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
        return $path . '?v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        if ($path === null || $path === '') {
            return '/assets/images/placeholder.svg';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    /** Old form input with an escaped fallback. */
    function old(string $key, mixed $default = ''): string
    {
        return e(\App\Session::old($key, $default));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \App\Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Csrf::token();
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return \App\Config::str('app.url') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return \App\Auth::user();
    }
}

if (!function_exists('is_active_nav')) {
    /** Adds the "active" class to the current nav item. */
    function is_active_nav(string ...$paths): string
    {
        $current = \App\Router::currentPath();
        foreach ($paths as $path) {
            if ($path === '/' ? $current === '/' : str_starts_with($current, $path)) {
                return ' active';
            }
        }
        return '';
    }
}

if (!function_exists('status_badge')) {
    /** Consistent status pill colours across every module. */
    function status_badge(string $status): string
    {
        $class = match ($status) {
            'delivered', 'successful', 'approved', 'active', 'paid', 'published', 'completed' => 'success',
            'processing', 'preparing', 'paid_pending', 'out_for_delivery', 'in_transit'       => 'info',
            'cancelled', 'rejected', 'failed', 'suspended', 'expired'                          => 'danger',
            'pending', 'pending_payment', 'pending_assignment', 'assigned', 'requested'       => 'warning',
            default                                                                      => 'secondary',
        };
        return '<span class="badge bg-' . $class . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
    }
}

if (!function_exists('active_class')) {
    function active_class(bool $condition, string $class = 'active'): string
    {
        return $condition ? ' ' . $class : '';
    }
}

if (!function_exists('star_rating')) {
    /** Renders a 5-star display from a 0–5 average. */
    function star_rating(float $rating, int $count = 0, bool $showCount = true): string
    {
        $full  = (int) floor($rating);
        $half  = ($rating - $full) >= 0.4;
        $html  = '<span class="text-warning" aria-label="Rated ' . number_format($rating, 1) . ' out of 5">';
        for ($i = 1; $i <= 5; $i++) {
            $html .= $i <= $full ? '<i class="bi bi-star-fill"></i>'
                : ($i === $full + 1 && $half ? '<i class="bi bi-star-half"></i>' : '<i class="bi bi-star"></i>');
        }
        $html .= '</span>';
        if ($showCount) {
            $html .= ' <small class="text-muted">(' . number_format($count) . ')</small>';
        }
        return $html;
    }
}
