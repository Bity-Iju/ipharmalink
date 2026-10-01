<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\OrderService;
use App\Session;
use App\ValidationException;

/**
 * REST-style JSON endpoints.
 *
 * These exist for two consumers: the storefront's fetch() calls today, and the
 * Android/iOS apps later. Every response is JSON, every mutation is CSRF- and
 * auth-checked, and no endpoint ever returns another user's data.
 */
final class ApiController extends Controller
{
    // =======================================================================
    //  Cart
    // =======================================================================

    /** POST /api/cart/add */
    public function cartAdd(Request $request): void
    {
        $this->cartAction($request, function (CartService $cart) use ($request): array {
            $cart->add($request->postInt('product_id'), max(1, $request->postInt('quantity', 1)));
            return ['message' => 'Added to your cart.'];
        });
    }

    /** POST /api/cart/update */
    public function cartUpdate(Request $request): void
    {
        $this->cartAction($request, function (CartService $cart) use ($request): array {
            $cart->updateQuantity($request->postInt('product_id'), max(1, $request->postInt('quantity', 1)));
            return ['message' => 'Cart updated.'];
        });
    }

    /** POST /api/cart/remove */
    public function cartRemove(Request $request): void
    {
        $this->cartAction($request, function (CartService $cart) use ($request): array {
            $cart->remove($request->postInt('product_id'));
            return ['message' => 'Removed from your cart.'];
        });
    }

    /** GET /api/cart */
    public function cart(Request $request): void
    {
        $cart     = new CartService();
        $contents = $cart->contents();
        $quote    = (new OrderService())->calculate('delivery');

        $groups = [];
        foreach ($contents['groups'] as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $items[] = [
                    'product_id' => $item['product_id'],
                    'slug'       => $item['slug'],
                    'name'       => $item['name'],
                    'image'      => upload_url($item['image']),
                    'quantity'   => $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'line_total' => (float) $item['line_total'],
                    'in_stock'   => (int) $item['stock_qty'],
                    'prescription' => (int) $item['requires_prescription'] === 1,
                ];
            }
            $groups[] = [
                'pharmacy_id'   => $group['pharmacy_id'],
                'pharmacy_name' => $group['pharmacy_name'],
                'pharmacy_slug' => $group['pharmacy_slug'],
                'subtotal'      => round((float) $group['subtotal'], 2),
                'items'         => $items,
            ];
        }

        Response::json([
            'ok'      => true,
            'count'   => $cart->rawCount(),
            'subtotal' => round((float) $contents['subtotal'], 2),
            'groups'  => $groups,
            'totals'  => [
                'subtotal'      => (float) $quote['subtotal'],
                'tax'           => (float) $quote['tax_total'],
                'delivery_fee'  => (float) $quote['delivery_fee'],
                'discount'      => (float) $quote['discount_total'],
                'total'         => (float) $quote['total'],
            ],
            'warnings' => $quote['warnings'],
        ]);
    }

    // =======================================================================
    //  Catalogue
    // =======================================================================

    /** GET /api/search/suggest?q= */
    public function searchSuggest(Request $request): void
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            Response::json(['ok' => true, 'items' => []]);
        }

        $items = [];
        foreach ((new CatalogService())->suggest($term, 8) as $row) {
            $items[] = [
                'id'            => (int) $row['id'],
                'slug'          => (string) $row['slug'],
                'name'          => (string) $row['name'],
                'brand_name'    => $row['brand_name'],
                'pharmacy_name' => (string) $row['pharmacy_name'],
                'price'         => (float) $row['price'],
                'discount_price' => $row['discount_price'] !== null ? (float) $row['discount_price'] : null,
                'image'         => upload_url($row['image']),
            ];
        }

        Response::json(['ok' => true, 'items' => $items]);
    }

    /** GET /api/products */
    public function products(Request $request): void
    {
        $filters = [
            'q'         => trim((string) $request->query('q', '')),
            'category'  => $request->queryInt('category', 0),
            'pharmacy'  => $request->queryInt('pharmacy', 0),
            'brand'     => $request->queryInt('brand', 0),
            'in_stock'  => (string) $request->query('in_stock', ''),
            'sort'      => (string) $request->query('sort', 'relevance'),
        ];

        $paginator = (new CatalogService())->search(
            $filters,
            max(1, $request->queryInt('page', 1)),
            max(1, min(60, $request->queryInt('per_page', 24)))
        );

        $items = [];
        foreach ($paginator->items() as $row) {
            $items[] = [
                'id'         => (int) $row['id'],
                'slug'       => (string) $row['slug'],
                'name'       => (string) $row['name'],
                'generic_name' => $row['generic_name'],
                'brand_name' => $row['brand_name'],
                'strength'   => $row['strength'],
                'price'      => (float) $row['price'],
                'discount_price' => $row['discount_price'] !== null ? (float) $row['discount_price'] : null,
                'stock_qty'  => (int) $row['stock_qty'],
                'prescription' => (int) $row['requires_prescription'] === 1,
                'pharmacy'   => ['name' => (string) $row['pharmacy_name'], 'slug' => (string) $row['pharmacy_slug']],
                'image'      => upload_url($row['image']),
            ];
        }

        Response::json([
            'ok'    => true,
            'data'  => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/products/{slug} */
    public function product(Request $request, array $params): void
    {
        $product = (new CatalogService())->findBySlug($this->param('slug', $params));

        if ($product === null) {
            Response::json(['ok' => false, 'message' => 'Product not found.'], 404);
        }

        Response::json([
            'ok' => true,
            'data' => [
                'id'          => (int) $product['id'],
                'sku'         => (string) $product['sku'],
                'slug'        => (string) $product['slug'],
                'name'        => (string) $product['name'],
                'generic_name' => $product['generic_name'],
                'brand_name'  => $product['brand_name'],
                'description' => $product['description'],
                'active_ingredient' => $product['active_ingredient'],
                'strength'    => $product['strength'],
                'dosage_form' => $product['dosage_form'],
                'pack_size'   => $product['pack_size'],
                'manufacturer' => $product['manufacturer'],
                'product_class' => (string) $product['product_class'],
                'prescription' => (int) $product['requires_prescription'] === 1,
                'price'       => (float) $product['price'],
                'discount_price' => $product['discount_price'] !== null ? (float) $product['discount_price'] : null,
                'stock_qty'   => (int) $product['stock_qty'],
                'rating_avg'  => (float) $product['rating_avg'],
                'rating_count' => (int) $product['rating_count'],
                'images'      => array_map(
                    static fn(array $i): string => upload_url($i['file_path']),
                    $product['images']
                ),
                'pharmacy'    => [
                    'name'  => (string) $product['pharmacy_name'],
                    'slug'  => (string) $product['pharmacy_slug'],
                    'city'  => $product['pharmacy_city'],
                    'phone' => $product['pharmacy_phone'],
                ],
            ],
        ]);
    }

    /** GET /api/pharmacies */
    public function pharmacies(Request $request): void
    {
        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL"
            ),
            static function (Database $db, int $perPage, int $offset): array {
                return $db->all(
                    "SELECT id, name, slug, logo, city, state, rating_avg, rating_count,
                            delivery_available, pickup_available, delivery_fee,
                            estimated_delivery_minutes, open_time, close_time
                     FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL
                     ORDER BY is_featured DESC, rating_avg DESC LIMIT {$perPage} OFFSET {$offset}"
                );
            },
            max(1, min(60, $request->queryInt('per_page', 20))),
            max(1, $request->queryInt('page', 1))
        );

        $data = [];
        foreach ($paginator->items() as $row) {
            $data[] = [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'slug'        => (string) $row['slug'],
                'logo'        => upload_url($row['logo']),
                'city'        => $row['city'],
                'state'       => $row['state'],
                'rating_avg'  => (float) $row['rating_avg'],
                'rating_count' => (int) $row['rating_count'],
                'delivery'    => (int) $row['delivery_available'] === 1,
                'pickup'      => (int) $row['pickup_available'] === 1,
                'delivery_fee' => (float) $row['delivery_fee'],
                'eta_minutes' => (int) $row['estimated_delivery_minutes'],
                'opens'       => substr((string) $row['open_time'], 0, 5),
                'closes'      => substr((string) $row['close_time'], 0, 5),
            ];
        }

        Response::json([
            'ok'   => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/categories */
    public function categories(Request $request): void
    {
        $rows = Database::instance()->all(
            'SELECT id, name, slug, parent_id, icon, image FROM categories
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY parent_id IS NULL DESC, sort_order ASC, name ASC'
        );

        Response::json(['ok' => true, 'data' => $rows]);
    }

    // =======================================================================
    //  Account (auth required)
    // =======================================================================

    /** GET /api/me */
    public function me(Request $request): void
    {
        $user = Auth::user();

        Response::json([
            'ok' => true,
            'data' => [
                'id'            => (int) $user['id'],
                'full_name'     => (string) $user['full_name'],
                'email'         => (string) $user['email'],
                'phone'         => $user['phone'],
                'role'          => (string) $user['role'],
                'role_label'    => $user['role_label'],
                'avatar'        => upload_url($user['profile_image']),
                'email_verified' => $user['email_verified_at'] !== null,
                'pharmacy_id'   => $user['pharmacy_id'],
                'permissions'   => Auth::permissions(),
            ],
        ]);
    }

    /** GET /api/notifications */
    public function notifications(Request $request): void
    {
        $userId = (int) Auth::id();

        $rows = Database::instance()->all(
            'SELECT id, type, title, body, link, is_read, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY id DESC LIMIT ' . max(1, min(50, $request->queryInt('limit', 20))),
            ['user_id' => $userId]
        );

        Response::json([
            'ok'     => true,
            'data'   => $rows,
            'unread' => \App\Services\NotificationService::unreadCount($userId),
        ]);
    }

    /** GET /api/orders */
    public function orders(Request $request): void
    {
        $rows = Database::instance()->all(
            'SELECT id, order_number, status, payment_status, fulfilment_method,
                    total, currency, created_at
             FROM orders WHERE customer_id = ?
             ORDER BY id DESC LIMIT ' . max(1, min(50, $request->queryInt('limit', 20))),
            ['customer_id' => Auth::id()]
        );

        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** GET /api/orders/{id} */
    public function order(Request $request, array $params): void
    {
        $orderId = (int) $this->param('id', $params);

        $order = (new OrderService())->findForCustomer($orderId, (int) Auth::id());
        if ($order === null) {
            Response::json(['ok' => false, 'message' => 'Order not found.'], 404);
        }

        $full = (new OrderService())->find($orderId);

        Response::json([
            'ok' => true,
            'data' => [
                'id'                => (int) $full['id'],
                'order_number'      => (string) $full['order_number'],
                'status'            => (string) $full['status'],
                'payment_status'    => (string) $full['payment_status'],
                'fulfilment_method' => (string) $full['fulfilment_method'],
                'subtotal'          => (float) $full['subtotal'],
                'delivery_fee'      => (float) $full['delivery_fee'],
                'discount_total'    => (float) $full['discount_total'],
                'tax_total'         => (float) $full['tax_total'],
                'total'             => (float) $full['total'],
                'currency'          => (string) $full['currency'],
                'address'           => $full['address_snapshot'] !== null
                    ? json_decode((string) $full['address_snapshot'], true) : null,
                'items'             => array_map(static fn(array $i): array => [
                    'name'     => (string) $i['product_name'],
                    'sku'      => (string) $i['sku'],
                    'image'    => upload_url($i['image']),
                    'quantity' => (int) $i['quantity'],
                    'unit_price' => (float) $i['unit_price'],
                    'line_total' => (float) $i['line_total'],
                    'prescription' => (int) $i['requires_prescription'] === 1,
                ], $full['items']),
                'pharmacies'        => array_map(static fn(array $s): array => [
                    'sub_order_number' => (string) $s['sub_order_number'],
                    'pharmacy'  => (string) $s['pharmacy_name'],
                    'status'    => (string) $s['status'],
                    'total'     => (float) $s['total'],
                ], $full['slices']),
                'history' => $full['history'],
            ],
        ]);
    }

    /** GET /api/addresses */
    public function addresses(Request $request): void
    {
        $rows = Database::instance()->all(
            'SELECT id, label, recipient_name, phone, state, city, address_line,
                    landmark, latitude, longitude, is_default
             FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC',
            ['user_id' => Auth::id()]
        );

        Response::json(['ok' => true, 'data' => $rows]);
    }

    // =======================================================================

    /**
     * Shared cart mutation handler: converts validation failures into a clean
     * 422 with the remaining stock, which the UI uses to correct the input.
     *
     * @param callable(CartService):array<string,mixed> $action
     */
    private function cartAction(Request $request, callable $action): void
    {
        if (!Csrf::verifyRequest($request)) {
            Response::json(['ok' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
        }

        $cart = new CartService();

        try {
            $payload = $action($cart);
        } catch (ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            $productId = $request->postInt('product_id');
            $remaining = Database::instance()->value(
                'SELECT stock_qty FROM products WHERE id = ?',
                ['id' => $productId]
            );

            Response::json([
                'ok'        => false,
                'message'   => implode(' ', $messages),
                'remaining' => $remaining === null ? 0 : (int) $remaining,
            ], 422);
        }

        $contents = $cart->contents();

        $lineTotals = [];
        foreach ($contents['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $lineTotals[(string) $item['product_id']] = (float) $item['line_total'];
            }
        }

        Response::json(array_merge($payload, [
            'ok'         => true,
            'count'      => $cart->rawCount(),
            'subtotal'   => round((float) $contents['subtotal'], 2),
            'line_totals' => $lineTotals,
        ]));
    }
}
