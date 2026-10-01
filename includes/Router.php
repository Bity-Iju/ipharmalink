<?php

/**
 * iPharmaLink :: Router
 * ---------------------------------------------------------------------------
 * A small, explicit router supporting:
 *   - Static routes        GET  /pharmacies
 *   - Parameter routes     GET  /product/{slug}
 *   - Wildcards            GET  /admin/{path:.*}
 *   - Method constraints    ->post('/cart/add', [CartController::class, 'add'])
 *   - Route middleware      ->get('/admin/x', 'auth,admin', Handler::class, 'x')
 *   - A simple {slug} => value map, injected as named arguments
 *
 * Matching is exact-first then pattern-based, so literal routes always beat
 * parameterised ones.
 */

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var list<array{method:string,segments:list<string>,middleware:list<string>,handler:mixed,paramNames:list<string>}> */
    private static array $routes = [];
    private static bool $dispatched = false;
    private static string $currentPath = '/';

    /** Current request path — used by nav helpers and breadcrumbs. */
    public static function currentPath(): string
    {
        return self::$currentPath;
    }

    public static function get(string $pattern, mixed $handler, array|string $middleware = []): void
    {
        self::add('GET', $pattern, $handler, $middleware);
    }

    public static function post(string $pattern, mixed $handler, array|string $middleware = []): void
    {
        self::add('POST', $pattern, $handler, $middleware);
    }

    public static function put(string $pattern, mixed $handler, array|string $middleware = []): void
    {
        self::add('PUT', $pattern, $handler, $middleware);
    }

    public static function delete(string $pattern, mixed $handler, array|string $middleware = []): void
    {
        self::add('DELETE', $pattern, $handler, $middleware);
    }

    /** Register the same handler for GET and POST (simple form endpoints). */
    public static function form(string $pattern, mixed $handler, array|string $middleware = []): void
    {
        self::add('GET', $pattern, $handler, $middleware);
        self::add('POST', $pattern, $handler, $middleware);
    }

    public static function add(string $method, string $pattern, mixed $handler, array|string $middleware = []): void
    {
        $segments = self::segments($pattern);
        $names     = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{(\w+)(?::(.+))?\}$/', $segment, $m) === 1) {
                $names[] = $m[1];
            }
        }

        self::$routes[] = [
            'method'     => strtoupper($method),
            'segments'   => $segments,
            'middleware' => self::normaliseMiddleware($middleware),
            'handler'    => $handler,
            'paramNames' => $names,
        ];
    }

    /** @param array<string,mixed> $routes [pattern => handler] */
    public static function group(array $routes, array|string $middleware = []): void
    {
        $middleware = self::normaliseMiddleware($middleware);
        foreach ($routes as $pattern => $handler) {
            if (is_array($handler)) {
                $method  = strtoupper((string) array_shift($handler));
                $target  = count($handler) === 1 ? $handler[0] : $handler;
                $mw      = array_merge($middleware, self::normaliseMiddleware($handler[1] ?? []));
                self::add($method, (string) $pattern, $target, $mw);
                continue;
            }
            self::add('GET', (string) $pattern, $handler, $middleware);
        }
    }

    // -----------------------------------------------------------------------

    public static function dispatch(): void
    {
        if (self::$dispatched) {
            return;
        }
        self::$dispatched = true;

        $request = new Request();
        $path     = $request->path();
        $method   = $request->method();
        self::$currentPath = $path;

        // Allow HTML forms to emulate PUT/DELETE via _method.
        if ($method === 'POST' && $request->has('_method')) {
            $override = strtoupper($request->input('_method', 'POST'));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $pathMatchedOtherMethod = false;

        foreach (self::$routes as $route) {
            $params = self::match($route['segments'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['method'] !== $method && !($route['method'] === 'GET' && $method === 'HEAD')) {
                $pathMatchedOtherMethod = true;
                continue;
            }

            self::applyMiddleware($route['middleware'], $request);

            $handler = $route['handler'];
            if (is_callable($handler)) {
                $handler(...array_values($params));
                return;
            }
            if (is_array($handler) && count($handler) === 2) {
                [$class, $action] = $handler;
                /** @var Controller $controller */
                $controller = new $class();
                $controller->{$action}($request, $params);
                return;
            }
            throw new \RuntimeException('Invalid route handler for ' . $path);
        }

        if ($pathMatchedOtherMethod) {
            header('Allow: GET, POST');
            throw HttpException::notFound('That URL does not accept this type of request.');
        }

        throw HttpException::notFound('We could not find the page you were looking for.');
    }

    /**
     * Match a request path against a route pattern.
     *
     * @param  list<string> $pattern
     * @return array<string,string>|null  path parameters, or null when no match
     */
    private static function match(array $pattern, string $path): ?array
    {
        $parts = self::segments($path);
        $params = [];

        foreach ($pattern as $i => $segment) {
            if (preg_match('/^\{(\w+)(?::(.+))?\}$/', $segment, $m) === 1) {
                $name       = $m[1];
                $constraint = $m[2] ?? null;

                if (!isset($parts[$i])) {
                    return null;
                }
                $value = $parts[$i];

                if ($constraint === '.*') {
                    // consume the remainder of the path
                    $params[$name] = rawurldecode(implode('/', array_slice($parts, $i)));
                    return $params;
                }
                if ($constraint !== null && preg_match('#^' . $constraint . '$#', $value) !== 1) {
                    return null;
                }
                $params[$name] = rawurldecode($value);
                continue;
            }

            if (!isset($parts[$i]) || $parts[$i] !== $segment) {
                return null;
            }
        }

        return count($parts) === count($pattern) ? $params : null;
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    /** @return list<string> */
    private static function normaliseMiddleware(array|string $middleware): array
    {
        if (is_string($middleware)) {
            $middleware = array_map('trim', explode(',', $middleware));
        }
        return array_values(array_filter($middleware, static fn($m) => $m !== ''));
    }

    private static function applyMiddleware(array $middleware, Request $request): void
    {
        foreach ($middleware as $alias) {
            $handler = Middleware::resolve($alias);
            if ($handler !== null) {
                $handler($request);
            }
        }
    }
}
