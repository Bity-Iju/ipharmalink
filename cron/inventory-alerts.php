<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/Env.php';
Env::load(APP_ROOT . '/.env');

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

App\Config::set(require APP_ROOT . '/config/config.php');

try {
    $result = (new App\Services\InventoryAlertService())->scan();
    printf(
        "Inventory alert scan complete: %d products scanned, %d alerts triggered, %d alerts resolved.\n",
        $result['scanned'],
        $result['triggered'],
        $result['resolved']
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Inventory alert scan failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}