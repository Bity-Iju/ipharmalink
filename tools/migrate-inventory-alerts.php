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
    $sql = file_get_contents(APP_ROOT . '/database/migrations/001_inventory_alerts.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read the inventory-alert migration file.');
    }

    $db = App\Database::instance();
    $db->pdo()->exec($sql);
    $exists = $db->value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'inventory_alerts'"
    );
    if ((int) $exists !== 1) {
        throw new RuntimeException('The inventory_alerts table was not created.');
    }

    echo "Inventory alert migration applied and verified.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Inventory alert migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}