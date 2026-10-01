<?php

/**
 * iPharmaLink :: Authentication
 * ---------------------------------------------------------------------------
 * Owns identity: who is signed in, and proving it. It does NOT decide what
 * an account may do — that is Permissions (RBAC). Keeping the two separate
 * means a delivery rider and a pharmacy owner with the same role id can
 * still be constrained by different business rules.
 *
 * All credential checks go through this one class so the audit trail
 * (login_attempts, audit_logs) cannot be bypassed.
 */

declare(strict_types=1);

namespace App;

use App\Services\AuditService;

final class Auth
{
    private const SESSION_USER_ID = 'auth.user_id';
    private const SESSION_FINGERPRINT = 'auth.fingerprint';
    private const SESSION_INTENDED_LOGIN = 'auth.intended_role';

    /** @var array<string,mixed>|null */
    private static ?array $cachedUser = null;
    private static bool $resolved = false;

    // -----------------------------------------------------------------------
    //  Current user
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }
        self::$resolved = true;

        $id = Session::get(self::SESSION_USER_ID);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return self::$cachedUser = null;
        }

        $user = Database::instance()->first(
            'SELECT u.*, r.name AS role, r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1',
            [(int) $id]
        );

        if ($user === null || $user['status'] !== 'active') {
            self::logout();
            return self::$cachedUser = null;
        }

        if (!self::fingerprintMatches()) {
            // Session copied to a different browser/IP — treat as hijack.
            Logger::security('Session fingerprint mismatch', ['user_id' => $user['id']]);
            self::logout();
            return self::$cachedUser = null;
        }

        $user['permissions'] = self::permissionsFor((int) $user['id']);
        $user['pharmacy_id'] = self::pharmacyIdFor((int) $user['id']);
        $user['supplier_id'] = $user['role'] === 'wholesale_supplier'
            ? self::supplierIdFor((int) $user['id'])
            : null;

        return self::$cachedUser = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, $roles, true);
    }

    public static function isSuperAdmin(): bool
    {
        return self::hasRole('super_admin');
    }

    public static function isCustomer(): bool
    {
        return self::hasRole('customer');
    }

    public static function isPharmacy(): bool
    {
        return self::hasRole('pharmacy_owner', 'pharmacy_staff');
    }

    public static function isSupplier(): bool
    {
        return self::hasRole('wholesale_supplier');
    }

    public static function isDelivery(): bool
    {
        return self::hasRole('delivery_personnel');
    }

    public static function isPharmacyOwner(): bool
    {
        return self::hasRole('pharmacy_owner');
    }

    public static function isPharmacyStaff(): bool
    {
        return self::hasRole('pharmacy_staff');
    }

    /** The pharmacy this user is scoped to (owner or staff), or null. */
    public static function pharmacyId(): ?int
    {
        $id = self::user()['pharmacy_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    public static function supplierId(): ?int
    {
        $id = self::user()['supplier_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        return self::user()['permissions'] ?? [];
    }

    public static function can(string $permission): bool
    {
        if (self::isSuperAdmin()) {
            return true;                     // super admin bypasses the matrix
        }
        return in_array($permission, self::permissions(), true);
    }

    // -----------------------------------------------------------------------
    //  Attempt / complete
    // -----------------------------------------------------------------------

    /**
     * Verify credentials. Never reveals whether the account exists.
     *
     * @return array<string,mixed>|null  the user row on success
     */
    public static function attempt(string $identifier, string $password): ?array
    {
        $db       = Database::instance();
        $ip       = (new Request())->ip();
        $hash     = strtolower(trim($identifier));

        $user = $db->first(
            'SELECT u.*, r.name AS role FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE (LOWER(u.email) = :h OR u.phone = :h2) AND u.deleted_at IS NULL
             LIMIT 1',
            ['h' => $hash, 'h2' => trim($identifier)]
        );

        // ---- brute force throttling ---------------------------------------
        if ($user !== null && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            self::logAttempt($hash, $ip, false);
            throw HttpException::tooManyRequests(
                'This account is temporarily locked after repeated failed sign-in attempts. Please try again later.'
            );
        }
        if (RateLimiter::exceeded('login', $ip)) {
            self::logAttempt($hash, $ip, false);
            throw HttpException::tooManyRequests('Too many sign-in attempts from this device. Please wait before trying again.');
        }
        RateLimiter::hit('login', $ip);

        // ---- uniform failure path -----------------------------------------
        $valid = $user !== null && password_verify($password, (string) $user['password_hash']);

        if (!$valid) {
            if ($user !== null) {
                self::registerFailure((int) $user['id']);
            }
            self::logAttempt($hash, $ip, false);
            // Constant-ish time to avoid user enumeration.
            password_verify($password, '$2y$12$usqZ1dM8HqkYb6h1pV1su1Om3N1oW2zH5bQpE0EeR3vI0jJ7V6n9m9K');
            return null;
        }

        // ---- rehash if the cost factor changed ---------------------------
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $db->update(
                'users',
                ['password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])],
                'id = ?',
                ['id' => $user['id']]
            );
        }

        if ($user['status'] !== 'active') {
            self::logAttempt($hash, $ip, false);
            throw HttpException::forbidden(match ($user['status']) {
                'pending'    => 'Your account is awaiting verification. Please check your email or contact support.',
                'suspended'  => 'Your account has been suspended. Please contact support for assistance.',
                'deactivated' => 'This account has been deactivated.',
                default      => 'Your account is not currently active.',
            });
        }

        // ---- pharmacy vendors must be approved before they can log in ----
        if (in_array($user['role'], ['pharmacy_owner', 'pharmacy_staff'], true)) {
            $pharmacy = $db->first('SELECT status, name FROM pharmacies WHERE owner_id = ? LIMIT 1', [$user['id']]);
            if ($pharmacy === null) {
                throw HttpException::forbidden('No pharmacy storefront is linked to this account.');
            }
            if ($pharmacy['status'] !== 'approved') {
                throw HttpException::forbidden(match ($pharmacy['status']) {
                    'pending'   => sprintf('"%s" is still pending verification by our compliance team. We will notify you once approved.', $pharmacy['name']),
                    'suspended' => sprintf('"%s" is currently suspended. Please contact support.', $pharmacy['name']),
                    'rejected'  => sprintf('The registration for "%s" was not approved. Please review the reason and reapply.', $pharmacy['name']),
                    default     => sprintf('"%s" is not currently active.', $pharmacy['name']),
                });
            }
        }

        if ($user['role'] === 'wholesale_supplier') {
            $supplier = $db->first(
                'SELECT status, name FROM suppliers WHERE owner_id = ? AND deleted_at IS NULL LIMIT 1',
                [$user['id']]
            );
            if ($supplier === null) {
                throw HttpException::forbidden('No supplier organization is linked to this account.');
            }
            if ($supplier['status'] !== 'approved') {
                throw HttpException::forbidden(match ($supplier['status']) {
                    'pending' => sprintf('"%s" is awaiting supplier verification.', $supplier['name']),
                    'under_review' => sprintf('"%s" is currently under review.', $supplier['name']),
                    'rejected' => sprintf('The registration for "%s" was not approved. Please review the reason and contact support.', $supplier['name']),
                    'suspended' => sprintf('"%s" is currently suspended. Please contact support.', $supplier['name']),
                    default => sprintf('"%s" is not currently active.', $supplier['name']),
                });
            }
        }

        self::logAttempt($hash, $ip, true);
        return $user;
    }

    /** Call after a successful attempt(). */
    public static function login(array $user, bool $remember = false): void
    {
        if (Config::bool('security.session_regen_on_login', true)) {
            Session::regenerate();
        }
        Csrf::rotate();

        Session::set(self::SESSION_USER_ID, (int) $user['id']);
        Session::set(self::SESSION_FINGERPRINT, self::fingerprint());

        Database::instance()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => (new Request())->ip(),
            'failed_logins' => 0,
            'locked_until'  => null,
        ], 'id = ?', ['id' => $user['id']]);

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }

        RateLimiter::clear('login');
        self::resetCache();

        (new AuditService())->log('auth.login', 'user', (int) $user['id'], 'User signed in');
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            (new AuditService())->log('auth.logout', 'user', $id, 'User signed out');
            Database::instance()->update('users', ['remember_token' => null], 'id = ?', ['id' => $id]);
        }
        self::resetCache();
        Session::destroy();
    }

    // -----------------------------------------------------------------------
    //  Passwords
    // -----------------------------------------------------------------------

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    private static function registerFailure(int $userId): void
    {
        $db     = Database::instance();
        $max    = Config::int('security.max_login_attempts', 5);
        $window = Config::int('security.lockout_minutes', 15);

        $db->run('UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?', ['id' => $userId]);

        $failed = (int) $db->value('SELECT failed_logins FROM users WHERE id = ?', ['id' => $userId]);
        if ($failed >= $max) {
            $db->update('users', [
                'failed_logins' => 0,
                'locked_until'  => date('Y-m-d H:i:s', time() + $window * 60),
            ], 'id = ?', ['id' => $userId]);
            Logger::security('Account locked after failed attempts', ['user_id' => $userId, 'attempts' => $failed]);
        }
    }

    private static function logAttempt(string $identifier, string $ip, bool $successful): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier' => substr($identifier, 0, 190),
                'ip_address' => $ip,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'successful' => $successful ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Could not record login attempt', ['error' => $e->getMessage()]);
        }
    }

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $token = $selector . ':' . hash('sha256', $validator);

        Database::instance()->update('users', ['remember_token' => $token], 'id = ?', ['id' => $userId]);

        setcookie('ipl_remember', $token, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off'),
        ]);
    }

    private static function fingerprint(): string
    {
        // User-agent only (not IP) so mobile networks do not log people out.
        return hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200));
    }

    private static function fingerprintMatches(): bool
    {
        $stored = Session::get(self::SESSION_FINGERPRINT);
        return is_string($stored) && hash_equals($stored, self::fingerprint());
    }

    /** @return list<string> */
    private static function permissionsFor(int $userId): array
    {
        $rows = Database::instance()->all(
            'SELECT DISTINCT p.name
             FROM users u
             JOIN roles r              ON r.id = u.role_id
             JOIN role_permissions rp  ON rp.role_id = r.id
             JOIN permissions p        ON p.id = rp.permission_id
             WHERE u.id = ?',
            ['id' => $userId]
        );
        return array_map(static fn(array $row): string => (string) $row['name'], $rows);
    }

    private static function pharmacyIdFor(int $userId): ?int
    {
        $db = Database::instance();

        $pharmacyId = $db->value('SELECT id FROM pharmacies WHERE owner_id = ? LIMIT 1', ['id' => $userId]);
        if ($pharmacyId !== null) {
            return (int) $pharmacyId;
        }
        $staffId = $db->value(
            'SELECT pharmacy_id FROM pharmacy_staff WHERE user_id = ? AND status = ? LIMIT 1',
            ['id' => $userId, 'status' => 'active']
        );
        return $staffId === null ? null : (int) $staffId;
    }

    private static function supplierIdFor(int $userId): ?int
    {
        $id = Database::instance()->value(
            'SELECT id FROM suppliers WHERE owner_id = ? AND deleted_at IS NULL LIMIT 1',
            [$userId]
        );
        return $id === null ? null : (int) $id;
    }

    public static function resetCache(): void
    {
        self::$cachedUser = null;
        self::$resolved    = false;
    }
}
