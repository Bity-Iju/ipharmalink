<?php

/**
 * Exercises every report query directly so SQL errors surface here with the
 * real message instead of a generic 500 page.
 *
 * Run from the project root:  php tools/check-reports.php
 */
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Controllers\Admin\ReportAdminController;

$db   = Database::instance();
$ctrl = new ReportAdminController();
$from = date('Y-m-d', strtotime('-29 days'));
$to   = date('Y-m-d');

$methods = [
    'salesQuery',
    'ordersQuery',
    'pharmaciesQuery',
    'customersQuery',
    'productsQuery',
    'commissionsQuery',
    'paymentsQuery',
    'deliveriesQuery',
    'refundsQuery',
    'inventoryQuery',
];

$fail = 0;

foreach ($methods as $method) {
    $ref = new ReflectionMethod($ctrl, $method);
    $ref->setAccessible(true);
    $spec = $ref->invoke($ctrl);

    $params          = $spec['params'];
    $params          = $ctrl->bindRange($params, $spec['sql'], $from, $to);

    // Both the COUNT projection and the data projection must be valid SQL.
    $countSql = $ctrl->countFor($spec['sql']);

    foreach (['count' => $countSql, 'rows' => $spec['sql'] . ' LIMIT 5'] as $label => $sql) {
        try {
            $db->all($sql, $params);
            printf("%-18s %-6s OK\n", $spec['method'], $label);
        } catch (Throwable $e) {
            $fail++;
            printf("%-18s %-6s FAIL: %s\n", $spec['method'], $label, $e->getMessage());
        }
    }
}

echo $fail === 0 ? "\nAll report queries valid.\n" : "\n$fail report query failure(s).\n";
exit($fail === 0 ? 0 : 1);
