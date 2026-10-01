<?php

/**
 * iPharmaLink :: Core bootstrap
 * ---------------------------------------------------------------------------
 * Single entry point responsibilities:
 *   1. Load environment + configuration
 *   2. Start a hardened session
 *   3. Register the autoloader
 *   4. Connect the database
 *   5. Hand control to the Router
 *
 * Nothing else belongs in this file. Keep it thin.
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('iPharmaLink requires PHP 8.0 or newer. You are running ' . PHP_VERSION);
}

define('APP_START', microtime(true));
define('APP_ROOT',  __DIR__);
define('APP_NAME',  'iPharmaLink');

// ---------------------------------------------------------------------------
//  1. Environment
// ---------------------------------------------------------------------------
require_once APP_ROOT . '/config/Env.php';
Env::load(APP_ROOT . '/.env');

// ---------------------------------------------------------------------------
//  2. Autoloader (PSR-4 for the App\ namespace)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = APP_ROOT . '/includes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------------
//  3. Configuration
// ---------------------------------------------------------------------------
$config = require APP_ROOT . '/config/config.php';
App\Config::set($config);

// ---------------------------------------------------------------------------
//  4. Error handling — never leak internals in production
// ---------------------------------------------------------------------------
$debug = App\Config::bool('app.debug');

ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

App\ErrorHandler::register($debug);

// ---------------------------------------------------------------------------
//  5. Database
// ---------------------------------------------------------------------------
App\Database::instance();

// ---------------------------------------------------------------------------
//  6. Session (hardened before any output)
// ---------------------------------------------------------------------------
App\Session::start();

// ---------------------------------------------------------------------------
//  7. Maintenance mode
// ---------------------------------------------------------------------------
if (App\Config::bool('system.maintenance_mode') && !App\Auth::isSuperAdmin() && PHP_SAPI !== 'cli') {
    http_response_code(503);
    $view = APP_ROOT . '/templates/errors/503.php';
    if (is_file($view)) {
        require $view;
    } else {
        echo 'The platform is under maintenance. Please check back shortly.';
    }
    exit;
}

// ---------------------------------------------------------------------------
//  8. Global helpers
// ---------------------------------------------------------------------------
require APP_ROOT . '/includes/helpers.php';

// ---------------------------------------------------------------------------
//  9. Routes
// ---------------------------------------------------------------------------
require APP_ROOT . '/includes/routes.php';

// ---------------------------------------------------------------------------
// 10. Dispatch
// ---------------------------------------------------------------------------
App\Router::dispatch();
