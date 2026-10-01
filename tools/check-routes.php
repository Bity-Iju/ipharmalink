<?php

/**
 * Verifies routed controller classes without dispatching a web request.
 * Run from the project root: php tools/check-routes.php
 */
define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = APP_ROOT . '/includes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$names = [
    'App\Controllers\Admin\CustomerAdminController',
    'App\Controllers\Admin\OrderAdminController',
    'App\Controllers\Admin\FinanceAdminController',
    'App\Controllers\Admin\CmsAdminController',
    'App\Controllers\Admin\ReportAdminController',
    'App\Controllers\Admin\AuditAdminController',
    'App\Controllers\Admin\SettingAdminController',
    'App\Controllers\Admin\PharmacyAdminController',
    'App\Controllers\Admin\ProductAdminController',
    'App\Controllers\Admin\CategoryAdminController',
    'App\Controllers\Admin\SupplierAdminController',
    'App\Controllers\Supplier\SupplierDashboardController',
];

$bad = 0;
foreach ($names as $fqcn) {
    $short = substr($fqcn, strrpos($fqcn, '\\') + 1);
    $ok    = class_exists($fqcn);
    if (!$ok) {
        $bad++;
    }
    printf("%-28s %s\n", $short, $ok ? 'OK' : 'MISSING');
}

echo $bad === 0 ? "\nAll route classes resolved.\n" : "\n$bad class(es) missing.\n";
exit($bad === 0 ? 0 : 1);
