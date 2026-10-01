<?php

/**
 * iPharmaLink :: Hardened session management
 * ---------------------------------------------------------------------------
 * Responsibilities:
 *   - Start the session with secure cookie flags (HttpOnly, SameSite, Secure)
 *   - Absolute + idle timeouts
 *   - Regenerate the ID on privilege change (login) and periodically
 *   - Flash message bag
 *   - Old-input bag for repopulating forms after a validation failure
 */

declare(strict_types=1);

namespace App;

final class Session
{
    private const IDLE_KEY    = '_last_activity';
    private const CREATED_KEY = '_created_at';
    private const FLASH_KEY   = '_flash';
    private const OLD_KEY     = '_old';
    private const INTENDED    = '_intended_url';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = Config::get('session.secure', 'auto');
        if ($secure === 'auto') {
            $secure = self::isHttps();
        }

        session_name(Config::str('session.name', 'ipharmalink_sid'));
        session_set_cookie_params([
            'lifetime' => 0,                       // session cookie
            'path'     => Config::str('session.path', '/'),
            'domain'   => Config::str('session.domain', ''),
            'secure'   => (bool) $secure,
            'httponly' => true,
            'samesite' => Config::str('session.samesite', 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');   // reject attacker-supplied IDs
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) Config::int('session.lifetime', 3600));

        session_start();

        $_SESSION[self::IDLE_KEY]    ??= time();
        $_SESSION[self::CREATED_KEY] ??= time();
        $_SESSION[self::FLASH_KEY]   ??= [];
        $_SESSION[self::OLD_KEY]     ??= [];

        self::enforceTimeout();
    }

    private static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443) {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private static function enforceTimeout(): void
    {
        $idleLimit = Config::int('session.lifetime', 3600);
        $last      = (int) ($_SESSION[self::IDLE_KEY] ?? time());

        if ($idleLimit > 0 && (time() - $last) > $idleLimit) {
            $expired = true;
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'][] = 'Your session expired due to inactivity. Please sign in again.';
            if ($expired) {
                unset($expired);
            }
        }
        $_SESSION[self::IDLE_KEY] = time();
    }

    // -----------------------------------------------------------------------
    //  Lifecycle
    // -----------------------------------------------------------------------

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION[self::IDLE_KEY] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name() ?: 'session', '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => (bool) $p['secure'],
                'httponly' => true,
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // -----------------------------------------------------------------------
    //  Generic access
    // -----------------------------------------------------------------------

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Read and remove — useful for one-shot values. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    // -----------------------------------------------------------------------
    //  Flash messages
    // -----------------------------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY][$type][] = $message;
    }

    public static function success(string $message): void
    {
        self::flash('success', $message);
    }

    public static function error(string $message): void
    {
        self::flash('error', $message);
    }

    public static function warning(string $message): void
    {
        self::flash('warning', $message);
    }

    public static function info(string $message): void
    {
        self::flash('info', $message);
    }

    /** @return array<string,list<string>> */
    public static function pullFlash(): array
    {
        $messages = $_SESSION[self::FLASH_KEY] ?? [];
        $_SESSION[self::FLASH_KEY] = [];
        return $messages;
    }

    // -----------------------------------------------------------------------
    //  Old input (repopulate a form after a failed POST)
    // -----------------------------------------------------------------------

    public static function flashInput(array $input): void
    {
        unset(
            $input[self::csrfKey()],
            $input['password'],
            $input['password_confirmation'],
            $input['current_password'],
            $input['new_password']
        );
        $_SESSION[self::OLD_KEY] = $input;
    }

    /** @return array<string,mixed> */
    public static function oldInput(): array
    {
        $old = $_SESSION[self::OLD_KEY] ?? [];
        $_SESSION[self::OLD_KEY] = [];
        return $old;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION[self::OLD_KEY][$key] ?? $default;
    }

    // -----------------------------------------------------------------------
    //  Cart / checkout helpers
    // -----------------------------------------------------------------------

    /** Stable identifier for guest carts. */
    public static function cartKey(): string
    {
        if (!isset($_SESSION['_cart_key'])) {
            $_SESSION['_cart_key'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_cart_key'];
    }

    public static function setIntendedUrl(string $url): void
    {
        $_SESSION[self::INTENDED] = $url;
    }

    public static function pullIntendedUrl(string $fallback = '/'): string
    {
        $url = $_SESSION[self::INTENDED] ?? null;
        unset($_SESSION[self::INTENDED]);
        return is_string($url) && Response::isInternal($url) ? $url : $fallback;
    }

    private static function csrfKey(): string
    {
        return Config::str('security.csrf_token_name', '_token');
    }
}
