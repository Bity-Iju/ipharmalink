<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use IpharmaLink\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && $path === '/health') {
        Database::connection()->query('SELECT 1');
        echo json_encode(['ok' => true, 'service' => 'ipharmalink']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
