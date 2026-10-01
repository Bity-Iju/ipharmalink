<?php

/**
 * iPharmaLink :: Middleware registry
 * ---------------------------------------------------------------------------
 * Aliases are applied in declaration order before a controller runs. Each
 * alias resolves to a closure receiving the Request; throwing an
 * HttpException short-circuits the request.
 *
 *   'auth'            must be signed in
 *   'guest'           must be signed out
 *   'admin'           must be super admin
 *   'pharmacy'        must be pharmacy owner or staff
 *   'pharmacy_owner'  must be pharmacy owner
 *   'delivery'        must be delivery personnel
 *   'customer'        must be a customer account
 *   'verified'        email must be verified
 *   'csrf'            verify CSRF token (implied on all unsafe methods)
 *   'throttle:login'  rate limit
 *   'throttle:search' rate limit
 */

declare(strict_types=1);

namespace App;

final class Middleware
{
    /** @var array<string,callable(Request):void> */
    private static array $custom = [];

    public static function register(string $alias, callable $handler): void
    {
        self::$custom[$alias] = $handler;
    }

    public static function resolve(string $alias): ?callable
    {
        // Namespaced custom middleware: 'My\Namespace@method'
        if (str_contains($alias, '@')) {
            [$class, $method] = explode('@', $alias, 2);
            $middlewareClass = str_contains($class, '\\') ? $class : 'App\\Middleware\\' . $class;
            return [new $middlewareClass(), $method];
        }

        if (isset(self::$custom[$alias])) {
            return self::$custom[$alias];
        }

        return match (true) {
            $alias === 'csrf'        => self::csrf(),
            $alias === 'auth'        => self::auth(),
            $alias === 'guest'       => self::guest(),
            $alias === 'admin'       => self::role(['super_admin']),
            $alias === 'pharmacy'    => self::role(['pharmacy_owner', 'pharmacy_staff']),
            $alias === 'pharmacy_owner' => self::role(['pharmacy_owner']),
            $alias === 'supplier'    => self::role(['wholesale_supplier']),
            $alias === 'delivery'    => self::role(['delivery_personnel']),
            $alias === 'customer'    => self::role(['customer']),
            $alias === 'verified'    => self::verified(),
            $alias === 'pharmacy.approved' => self::approvedPharmacy(),
            $alias === 'supplier.approved' => self::approvedSupplier(),
            str_starts_with($alias, 'throttle:') => self::throttle(substr($alias, 9)),
            str_starts_with($alias, 'perm:')    => self::permission(substr($alias, 5)),
            default => null,
        };
    }

    // -----------------------------------------------------------------------

    private static function csrf(): callable
    {
        return static function (Request $request): void {
            if (
                !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
                && !Csrf::verifyRequest($request)
            ) {
                Logger::security('CSRF token mismatch', [
                    'path' => $request->path(),
                    'ip'   => $request->ip(),
                ]);
                throw HttpException::forbidden('Your session token has expired. Please refresh the page and try again.');
            }
        };
    }

    private static function auth(): callable
    {
        return static function (Request $request): void {
            if (Auth::guest()) {
                if ($request->wantsJson()) {
                    throw HttpException::unauthorized('Please sign in to continue.');
                }
                Session::setIntendedUrl($request->method() === 'GET' ? $request->path() : '/');
                Session::warning('Please sign in to continue.');
                Response::redirect('/login');
            }
        };
    }

    private static function guest(): callable
    {
        return static function (Request $request): void {
            if (Auth::check()) {
                Response::redirect(Auth::isSuperAdmin() ? '/admin/dashboard'
                    : (Auth::isPharmacy() ? '/pharmacy/dashboard'
                        : (Auth::isSupplier() ? '/supplier/dashboard'
                            : (Auth::isDelivery() ? '/delivery/dashboard' : '/account'))));
            }
        };
    }

    /** @param list<string> $roles */
    private static function role(array $roles): callable
    {
        return static function (Request $request) use ($roles): void {
            if (Auth::guest()) {
                Session::setIntendedUrl($request->path());
                Session::warning('Please sign in to continue.');
                Response::redirect('/login');
            }
            if (!Auth::hasRole(...$roles)) {
                Logger::security('Role access denied', [
                    'user'  => Auth::id(),
                    'role'  => Auth::role(),
                    'path'  => $request->path(),
                ]);
                throw HttpException::forbidden('You do not have access to this area.');
            }
        };
    }

    private static function permission(string $permission): callable
    {
        return static function () use ($permission): void {
            if (!Auth::can($permission)) {
                throw HttpException::forbidden('You do not have permission to perform this action.');
            }
        };
    }

    private static function verified(): callable
    {
        return static function (Request $request): void {
            $user = Auth::user();
            if ($user !== null && $user['email_verified_at'] === null) {
                Session::warning('Please verify your email address to continue.');
                Response::redirect('/verify-email');
            }
        };
    }

    private static function approvedPharmacy(): callable
    {
        return static function (Request $request): void {
            $pharmacyId = Auth::pharmacyId();
            if ($pharmacyId === null) {
                throw HttpException::forbidden('No pharmacy is linked to your account.');
            }
            $status = Database::instance()->value('SELECT status FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]);
            if ($status !== 'approved') {
                throw HttpException::forbidden('Your pharmacy account is not currently active. Contact support for help.');
            }
        };
    }

    private static function approvedSupplier(): callable
    {
        return static function (): void {
            $supplierId = Auth::supplierId();
            if ($supplierId === null) {
                throw HttpException::forbidden('No supplier organization is linked to your account.');
            }
            $status = Database::instance()->value('SELECT status FROM suppliers WHERE id = ?', ['id' => $supplierId]);
            if ($status !== 'approved') {
                throw HttpException::forbidden('Your supplier account is not currently approved.');
            }
        };
    }

    private static function throttle(string $action): callable
    {
        return static function () use ($action): void {
            RateLimiter::hit($action);
        };
    }
}
