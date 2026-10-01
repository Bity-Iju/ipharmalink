<?php

/**
 * iPharmaLink :: CSRF protection
 * ---------------------------------------------------------------------------
 * One token per session, compared with hash_equals(). Every state-changing
 * request (POST/PUT/PATCH/DELETE) is verified by the Router before the
 * controller runs, so a new form must call csrf_field() to render the input.
 */

declare(strict_types=1);

namespace App;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Hidden input for forms. */
    public static function field(): string
    {
        $name = Config::str('security.csrf_token_name', '_token');
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Meta tag for JS-driven requests (fetch/ajax). */
    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $candidate): bool
    {
        $expected = $_SESSION[self::KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($candidate) || $candidate === '') {
            return false;
        }
        return hash_equals($expected, $candidate);
    }

    public static function verifyRequest(Request $request): bool
    {
        $name = Config::str('security.csrf_token_name', '_token');
        $sent = $request->post($name) ?? $request->header('X-CSRF-TOKEN');
        return self::verify(is_string($sent) ? $sent : null);
    }

    /** Rotate the token — called on login/logout. */
    public static function rotate(): string
    {
        unset($_SESSION[self::KEY]);
        return self::token();
    }
}
