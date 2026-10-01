<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use IpharmaLink\Auth;
use IpharmaLink\Database;
use IpharmaLink\SupplierProducts;
use IpharmaLink\WholesaleCart;
use IpharmaLink\WholesaleOrders;
use IpharmaLink\Payments;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

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
    $GLOBALS['db'] = $db;
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Public endpoints
    if ($method === 'GET' && $path === 'health') {
        $db->query('SELECT 1');
        respond(['ok' => true, 'service' => 'ipharmalink']);
    }
    if ($method === 'POST' && $path === 'api/auth/register') respond(Auth::register($db, body()), 201);
    if ($method === 'POST' && $path === 'api/auth/login') respond(Auth::login($db, body()));

    // Protected endpoints
    $user = Auth::userFromBearer($db, $_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if (!$user) respond(['error' => 'Authentication required'], 401);

    if ($method === 'GET' && $path === 'api/me') respond(['user' => $user]);
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

    // Supplier endpoints
    if ($user['role_name'] === 'supplier') {
        $supplierId = $db->prepare('SELECT id FROM organizations WHERE owner_user_id = ? AND type = "supplier" LIMIT 1')->execute([$user['id']])->fetchColumn();
        if (!$supplierId) respond(['error' => 'Supplier profile not found'], 403);

        if ($method === 'POST' && $path === 'api/supplier/products') {
            respond(SupplierProducts::create($db, (int) $supplierId, body()), 201);
        }
        if ($method === 'GET' && $path === 'api/supplier/products') {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            respond(['data' => SupplierProducts::list($db, (int) $supplierId, $limit, $offset)]);
        }
        if ($method === 'PUT' && preg_match('#api/supplier/products/(\d+)/pricing#', $path, $m)) {
            SupplierProducts::updatePricing($db, (int) $m[1], (int) $supplierId, body()['tiers'] ?? []);
            respond(['success' => true]);
        }
        if ($method === 'PUT' && preg_match('#api/supplier/orders/(\d+)/status#', $path, $m)) {
            WholesaleOrders::updateStatus($db, (int) $m[1], (int) $supplierId, (string) body()['status']);
            respond(['success' => true]);
        }
        if ($method === 'GET' && preg_match('#api/supplier/orders/(\d+)#', $path, $m)) {
            respond(WholesaleOrders::getOrder($db, (int) $m[1], (int) $supplierId));
        }
    }

    // Retail pharmacy and customer endpoints
    if ($user['role_name'] === 'retail_pharmacy' || $user['role_name'] === 'customer') {
        if ($method === 'POST' && $path === 'api/wholesale/cart') {
            respond(WholesaleCart::getOrCreate($db, (int) $user['id']), 201);
        }
        if ($method === 'POST' && $path === 'api/wholesale/cart/items') {
            $data = body();
            $result = WholesaleCart::addItem($db, (int) $data['cart_id'], (int) $user['id'], (int) $data['product_id'], (int) $data['packaging_id'], (int) $data['quantity']);
            respond($result, 201);
        }
        if ($method === 'GET' && preg_match('#api/wholesale/cart/(\d+)#', $path, $m)) {
            respond(WholesaleCart::getCart($db, (int) $m[1], (int) $user['id']));
        }
        if ($method === 'DELETE' && preg_match('#api/wholesale/cart/(\d+)/items/(\d+)#', $path, $m)) {
            WholesaleCart::removeItem($db, (int) $m[1], (int) $user['id'], (int) $m[2]);
            respond(['success' => true]);
        }
        if ($method === 'POST' && $path === 'api/wholesale/orders') {
            respond(WholesaleOrders::checkout($db, (int) $user['id'], body()), 201);
        }
        if ($method === 'GET' && preg_match('#api/wholesale/orders/(\d+)#', $path, $m)) {
            $buyerOrg = $db->prepare('SELECT id FROM organizations WHERE owner_user_id = ? AND type IN ("retail_pharmacy", "supplier") LIMIT 1')->execute([$user['id']])->fetchColumn();
            respond(WholesaleOrders::getOrder($db, (int) $m[1], (int) ($buyerOrg ?: 0)));
        }
        if ($method === 'POST' && $path === 'api/payments') {
            respond(Payments::create($db, (int) $user['id'], body()), 201);
        }
        if ($method === 'POST' && $path === 'api/payments/verify') {
            respond(Payments::verifyAndMarkPaid($db, (string) body()['reference']));
        }
    }

    respond(['error' => 'Route not found'], 404);
} catch (\PDOException $e) {
    error_log($e->getMessage());
    respond(['error' => 'Database error'], 500);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 400);
}
