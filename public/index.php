<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use IpharmaLink\Auth;
use IpharmaLink\Database;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') exit;

function body(): array {
    $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = Database::connection();
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && $path === 'health') {
        $db->query('SELECT 1');
        respond(['ok' => true, 'service' => 'ipharmalink']);
    }
    if ($method === 'POST' && $path === 'api/auth/register') respond(Auth::register($db, body()), 201);
    if ($method === 'POST' && $path === 'api/auth/login') respond(Auth::login($db, body()));

    $user = Auth::userFromBearer($db, $_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if ($method === 'GET' && $path === 'api/me') {
        if (!$user) respond(['error' => 'Authentication required'], 401);
        respond(['user' => $user]);
    }
    if ($method === 'GET' && $path === 'api/products') {
        $search = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT p.id, p.name, p.generic_name, p.brand, p.sku, p.prescription_required, o.business_name AS supplier, MIN(w.price) AS starting_price FROM products p JOIN organizations o ON o.id = p.supplier_id AND o.verification_status = "approved" LEFT JOIN wholesale_price_tiers w ON w.product_id = p.id WHERE p.status = "active"';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.generic_name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)';
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term];
        }
        $sql .= ' GROUP BY p.id, p.name, p.generic_name, p.brand, p.sku, p.prescription_required, o.business_name ORDER BY p.created_at DESC LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        respond(['data' => $stmt->fetchAll()]);
    }
    respond(['error' => 'Route not found'], 404);
} catch (\PDOException $e) {
    error_log($e->getMessage());
    respond(['error' => 'Database error'], 500);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 400);
}
