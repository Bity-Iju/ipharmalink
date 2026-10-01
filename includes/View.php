<?php

/**
 * iPharmaLink :: View renderer
 * ---------------------------------------------------------------------------
 * Renders templates/templates/*.php inside a layout. Variables are extracted
 * into scope, escaped explicitly in the template (there is no auto-escaping
 * magic) — every echo in a template goes through e() unless it is trusted
 * HTML (CMS page bodies, review text after sanitising).
 */

declare(strict_types=1);

namespace App;

final class View
{
    /** @var array<string,mixed> Shared bindings injected into every view. */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::prefixLocalPaths(self::capture($layout, array_merge($data, ['content' => $content])));
    }

    /**
     * Render a template to a string (used for emails, CSV, PDF fragments).
     *
     * @param array<string,mixed> $data
     */
    public static function capture(string $template, array $data = []): string
    {
        $path = self::path($template);
        if ($path === null) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        ob_start();
        require $path;
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public static function exists(string $template, array $data = []): bool
    {
        return self::path($template) !== null;
    }

    public static function include(string $template, array $data = []): void
    {
        $path = self::path($template);
        if ($path === null) {
            throw new \RuntimeException("View not found: {$template}");
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        require $path;
    }

    private static function path(string $template): ?string
    {
        // Reject traversal attempts outright.
        if (str_contains($template, '..')) {
            return null;
        }

        // Accept both `auth.login` and `auth/login`.
        $template = str_replace('.', '/', $template);

        $file = APP_ROOT . '/templates/' . ltrim($template, '/') . '.php';
        return is_file($file) ? $file : null;
    }

    private static function prefixLocalPaths(string $html): string
    {
        $basePath = Config::basePath();
        if ($basePath === '') {
            return $html;
        }

        return preg_replace_callback(
            '/\b(href|src|action|poster|formaction|data-bs-target|data-target|data-url)="(\/(?!\/)[^"]*)"/i',
            static function (array $match) use ($basePath): string {
                $path = $match[2];
                if ($path === $basePath || str_starts_with($path, $basePath . '/')) {
                    return $match[0];
                }
                return $match[1] . '="' . $basePath . ($path === '/' ? '/' : $path) . '"';
            },
            $html
        ) ?? $html;
    }
}
