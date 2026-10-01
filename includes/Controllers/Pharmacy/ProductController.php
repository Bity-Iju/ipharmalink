<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Session;
use App\Upload;
use App\View;

/**
 * Pharmacy product management. Every query is scoped by pharmacy_id, taken
 * from the session — never from the request — so a vendor cannot reach another
 * pharmacy's catalogue by editing a URL or a hidden field.
 */
final class ProductController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /pharmacy/products
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();
        $search     = trim((string) $request->query('q', ''));
        $categoryId = $request->queryInt('category', 0);
        $status     = (string) $request->query('status', '');

        [$where, $params] = [['p.pharmacy_id = :pharm', 'p.deleted_at IS NULL'], ['pharm' => $pharmacyId]];

        if ($search !== '') {
            $where[]       = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.generic_name LIKE :q3 OR p.brand_name LIKE :q4)';
            $like          = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['q1']  = $like;
            $params['q2']  = $like;
            $params['q3']  = $like;
            $params['q4']  = $like;
        }
        if ($categoryId > 0) {
            $where[]           = '(p.category_id = :cat OR p.subcategory_id = :cat2)';
            $params['cat']    = $categoryId;
            $params['cat2']   = $categoryId;
        }
        if ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        } elseif ($status === 'low_stock') {
            $where[] = 'p.stock_qty > 0 AND p.stock_qty <= p.min_stock_level';
        } elseif ($status === 'out_of_stock') {
            $where[] = 'p.stock_qty = 0';
        } elseif ($status === 'expiring') {
            $where[] = 'p.expiry_date IS NOT NULL AND p.expiry_date <= :soon';
            $params['soon'] = date('Y-m-d', strtotime('+90 days'));
        } elseif ($status === 'expired') {
            $where[] = 'p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()';
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM products p WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT p.*,
                            (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                              ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image,
                            c.name AS category_name
                     FROM products p
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE {$clause}
                     ORDER BY p.updated_at DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(20),
            $this->page()
        );

        $this->view('pharmacy/products/index', [
            'title'      => 'Products',
            'heading'    => 'Products',
            'sidebar'    => View::capture('pharmacy/partials/sidebar'),
            'paginator'  => $paginator,
            'search'     => $search,
            'status'     => $status,
            'categoryId' => $categoryId,
            'categories' => $this->categoryTree(),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/products/create
    // -----------------------------------------------------------------------
    public function create(Request $request): void
    {
        $this->view('pharmacy/products/form', [
            'title'      => 'Add a product',
            'heading'    => 'Add a product',
            'sidebar'    => View::capture('pharmacy/partials/sidebar'),
            'product'    => null,
            'categories' => $this->categoryTree(),
            'brands'     => $this->brands(),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products
    // -----------------------------------------------------------------------
    public function store(Request $request): void
    {
        $data = $this->validate(
            [
                'name'             => 'required|string|min:3|max:200',
                'generic_name'     => 'nullable|string|max:160',
                'brand_name'       => 'nullable|string|max:120',
                'category_id'      => 'nullable|exists:categories,id',
                'subcategory_id'   => 'nullable|exists:categories,id',
                'description'      => 'required|string|min:20|max:5000',
                'active_ingredient' => 'nullable|string|max:255',
                'strength'         => 'nullable|string|max:80',
                'dosage_form'      => 'nullable|string|max:80',
                'pack_size'        => 'nullable|string|max:80',
                'manufacturer'     => 'nullable|string|max:160',
                'price'            => 'required|decimal|min:0',
                'discount_price'   => 'nullable|decimal|min:0',
                'tax_rate'         => 'nullable|decimal|between:0,100',
                'stock_qty'        => 'required|integer|min:0',
                'min_stock_level'  => 'required|integer|min:0',
                'batch_number'     => 'nullable|string|max:60',
                'manufacturing_date' => 'nullable|date',
                'expiry_date'      => 'nullable|date|after_or_equal:today',
                'requires_prescription' => 'nullable|bool',
                'product_class'    => 'required|in:otc,prescription,restricted,device,supplement,cosmetic',
                'is_featured'      => 'nullable|bool',
                'is_visible'       => 'nullable|bool',
            ],
            $request->all(),
            'pharmacy/products/form',
            '/pharmacy/products'
        );

        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $sku = $this->uniqueSku(
            $this->request->input('sku') ?: 'IPL-' . strtoupper(bin2hex(random_bytes(3)))
        );

        $productId = $db->transaction(function () use ($db, $data, $pharmacyId, $sku, $request): int {
            $id = $db->insert('products', [
                'pharmacy_id'        => $pharmacyId,
                'sku'                => $sku,
                'slug'               => $this->uniqueSlug((string) $data['name']),
                'name'               => $data['name'],
                'generic_name'       => $data['generic_name'] ?? null,
                'brand_name'         => $data['brand_name'] ?? null,
                'category_id'        => $data['category_id'] ?? null,
                'subcategory_id'     => $data['subcategory_id'] ?? null,
                'description'        => $data['description'],
                'active_ingredient'  => $data['active_ingredient'] ?? null,
                'strength'           => $data['strength'] ?? null,
                'dosage_form'        => $data['dosage_form'] ?? null,
                'pack_size'          => $data['pack_size'] ?? null,
                'manufacturer'       => $data['manufacturer'] ?? null,
                'requires_prescription' => !empty($data['requires_prescription']) ? 1 : 0,
                'product_class'      => $data['product_class'],
                'price'              => round((float) $data['price'], 2),
                'discount_price'     => isset($data['discount_price']) && (float) $data['discount_price'] > 0
                    ? round((float) $data['discount_price'], 2) : null,
                'tax_rate'           => (float) ($data['tax_rate'] ?? 0),
                'stock_qty'          => (int) $data['stock_qty'],
                'min_stock_level'    => (int) $data['min_stock_level'],
                'batch_number'       => $data['batch_number'] ?? null,
                'manufacturing_date' => $data['manufacturing_date'] ?? null,
                'expiry_date'        => $data['expiry_date'] ?? null,
                'is_active'          => 1,
                'is_featured'        => !empty($data['is_featured']) ? 1 : 0,
                'is_visible'         => !empty($data['is_visible']) ? 1 : 0,
                'is_approved'        => \App\Setting::getBool('catalog.require_product_approval', false) ? 0 : 1,
            ]);

            $this->saveImages($id, $request, $data);
            $this->recordOpeningStock($id, (int) $data['stock_qty'], $data);

            return $id;
        });

        (new AuditService())->log('product.created', 'product', $productId, sprintf('Product "%s" created', $data['name']));

        Session::success('Product added to your catalogue.');
        Response::redirect('/pharmacy/products');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/products/edit/{id}
    // -----------------------------------------------------------------------
    public function edit(Request $request, array $params): void
    {
        $product = $this->ownedProduct((int) $this->param('id', $params));

        $this->view('pharmacy/products/form', [
            'title'      => 'Edit ' . (string) $product['name'],
            'heading'    => 'Edit product',
            'sidebar'    => View::capture('pharmacy/partials/sidebar'),
            'product'    => $product,
            'images'     => Database::instance()->all(
                'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC',
                ['product_id' => $product['id']]
            ),
            'categories' => $this->categoryTree(),
            'brands'     => $this->brands(),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products/{id}
    // -----------------------------------------------------------------------
    public function update(Request $request, array $params): void
    {
        $id      = (int) $this->param('id', $params);
        $product = $this->ownedProduct($id);

        $data = $this->validate(
            [
                'name'             => 'required|string|min:3|max:200',
                'generic_name'     => 'nullable|string|max:160',
                'brand_name'       => 'nullable|string|max:120',
                'category_id'      => 'nullable|exists:categories,id',
                'subcategory_id'   => 'nullable|exists:categories,id',
                'description'      => 'required|string|min:20|max:5000',
                'active_ingredient' => 'nullable|string|max:255',
                'strength'         => 'nullable|string|max:80',
                'dosage_form'      => 'nullable|string|max:80',
                'pack_size'        => 'nullable|string|max:80',
                'manufacturer'     => 'nullable|string|max:160',
                'price'            => 'required|decimal|min:0',
                'discount_price'   => 'nullable|decimal|min:0',
                'tax_rate'         => 'nullable|decimal|between:0,100',
                'min_stock_level'  => 'required|integer|min:0',
                'batch_number'     => 'nullable|string|max:60',
                'manufacturing_date' => 'nullable|date',
                'expiry_date'      => 'nullable|date',
                'requires_prescription' => 'nullable|bool',
                'product_class'    => 'required|in:otc,prescription,restricted,device,supplement,cosmetic',
                'is_active'        => 'nullable|bool',
                'is_featured'      => 'nullable|bool',
                'is_visible'       => 'nullable|bool',
            ],
            $request->all(),
            'pharmacy/products/form',
            '/pharmacy/products'
        );

        $db      = Database::instance();
        $newQty  = $request->has('stock_qty') ? max(0, $request->postInt('stock_qty')) : (int) $product['stock_qty'];

        // A discount above list price would be a price increase, not a discount.
        $discount = isset($data['discount_price']) && (float) $data['discount_price'] > 0
            && (float) $data['discount_price'] < (float) $data['price']
            ? round((float) $data['discount_price'], 2) : null;

        $update = [
            'name'               => $data['name'],
            'generic_name'       => $data['generic_name'] ?? null,
            'brand_name'         => $data['brand_name'] ?? null,
            'category_id'        => $data['category_id'] ?? null,
            'subcategory_id'     => $data['subcategory_id'] ?? null,
            'description'        => $data['description'],
            'active_ingredient'  => $data['active_ingredient'] ?? null,
            'strength'           => $data['strength'] ?? null,
            'dosage_form'        => $data['dosage_form'] ?? null,
            'pack_size'          => $data['pack_size'] ?? null,
            'manufacturer'       => $data['manufacturer'] ?? null,
            'requires_prescription' => !empty($data['requires_prescription']) ? 1 : 0,
            'product_class'      => $data['product_class'],
            'price'              => round((float) $data['price'], 2),
            'discount_price'     => $discount,
            'tax_rate'           => (float) ($data['tax_rate'] ?? 0),
            'min_stock_level'    => (int) $data['min_stock_level'],
            'batch_number'       => $data['batch_number'] ?? null,
            'manufacturing_date' => $data['manufacturing_date'] ?? null,
            'expiry_date'        => $data['expiry_date'] ?? null,
            'is_active'          => $request->bool('is_active') ? 1 : 0,
            'is_featured'        => !empty($data['is_featured']) ? 1 : 0,
            'is_visible'         => !empty($data['is_visible']) ? 1 : 0,
        ];

        $db->transaction(function () use ($db, $id, $update, $product, $newQty, $request, $data): void {
            $db->update('products', $update, 'id = ?', ['id' => $id]);
            $this->saveImages($id, $request, $data);

            // Stock changes made on the edit form go through the ledger so the
            // movement is attributed and reversible.
            if ($newQty !== (int) $product['stock_qty']) {
                (new InventoryService())->adjust(
                    $id,
                    $newQty - (int) $product['stock_qty'],
                    InventoryService::TYPE_ADJUSTMENT,
                    trim((string) $request->input('stock_reason', 'Adjusted from the product editor'))
                );
            }
        });

        $audit = new AuditService();
        if ((float) $product['price'] !== (float) $update['price']) {
            $audit->log('product.price_updated', 'product', $id, sprintf(
                'Price for "%s" changed from %s to %s',
                (string) $product['name'],
                money((float) $product['price']),
                money((float) $update['price'])
            ), ['old' => ['price' => $product['price']], 'new' => ['price' => $update['price']]]);
        }
        $audit->log('product.updated', 'product', $id, sprintf('Product "%s" updated', $update['name']));

        Session::success('Product updated.');
        Response::redirect('/pharmacy/products');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products/{id}/delete
    // -----------------------------------------------------------------------
    public function destroy(Request $request, array $params): void
    {
        $id      = (int) $this->param('id', $params);
        $product = $this->ownedProduct($id);

        // Soft delete: order history references the product row.
        Database::instance()->update('products', [
            'deleted_at'   => date('Y-m-d H:i:s'),
            'is_active'    => 0,
            'is_visible'   => 0,
        ], 'id = ?', ['id' => $id]);

        (new AuditService())->log('product.deleted', 'product', $id, sprintf('Product "%s" removed', (string) $product['name']));

        Session::success('Product removed from your catalogue.');
        Response::redirect('/pharmacy/products');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products/{id}/duplicate
    // -----------------------------------------------------------------------
    public function duplicate(Request $request, array $params): void
    {
        $id      = (int) $this->param('id', $params);
        $product = $this->ownedProduct($id);
        $db      = Database::instance();

        $newId = $db->insert('products', [
            'pharmacy_id'   => (int) Auth::pharmacyId(),
            'sku'           => $product['sku'] . '-C' . random_int(10, 99),
            'slug'          => $this->uniqueSlug((string) $product['name'] . ' copy'),
            'name'          => (string) $product['name'] . ' (copy)',
            'generic_name'  => $product['generic_name'],
            'brand_name'    => $product['brand_name'],
            'category_id'   => $product['category_id'],
            'subcategory_id' => $product['subcategory_id'],
            'description'   => $product['description'],
            'active_ingredient' => $product['active_ingredient'],
            'strength'      => $product['strength'],
            'dosage_form'   => $product['dosage_form'],
            'pack_size'     => $product['pack_size'],
            'manufacturer'  => $product['manufacturer'],
            'requires_prescription' => (int) $product['requires_prescription'],
            'product_class' => (string) $product['product_class'],
            'price'         => (float) $product['price'],
            'discount_price' => $product['discount_price'],
            'tax_rate'      => (float) $product['tax_rate'],
            'stock_qty'     => 0,
            'min_stock_level' => (int) $product['min_stock_level'],
            'is_active'     => 0,
            'is_visible'    => 0,
            'is_approved'   => \App\Setting::getBool('catalog.require_product_approval', false) ? 0 : 1,
        ]);

        foreach ($db->all('SELECT * FROM product_images WHERE product_id = ?', ['product_id' => $id]) as $image) {
            $db->insert('product_images', [
                'product_id' => $newId,
                'file_path'  => $image['file_path'],
                'is_primary' => 0,
                'sort_order' => (int) $image['sort_order'],
            ]);
        }

        Session::success('Product duplicated. It is inactive with zero stock until you set both.');
        Response::redirect('/pharmacy/products/edit/' . $newId);
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products/{id}/toggle
    // -----------------------------------------------------------------------
    public function toggle(Request $request, array $params): void
    {
        $id      = (int) $this->param('id', $params);
        $product = $this->ownedProduct($id);
        $active  = (int) $product['is_active'] === 1 ? 0 : 1;

        Database::instance()->update('products', ['is_active' => $active], 'id = ?', ['id' => $id]);

        (new AuditService())->log(
            $active ? 'product.activated' : 'product.deactivated',
            'product',
            $id,
            sprintf('"%s" %s', (string) $product['name'], $active ? 'activated' : 'deactivated')
        );

        Session::success($active ? 'Product is now live on your storefront.' : 'Product hidden from customers.');
        Response::back('/pharmacy/products');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/products/bulk
    // -----------------------------------------------------------------------
    public function bulk(Request $request): void
    {
        $action     = (string) $request->input('bulk_action', '');
        $productIds = $request->array('product_ids');
        $productIds = array_values(array_filter(array_map('intval', $productIds)));

        if ($productIds === []) {
            Session::error('Please select at least one product.');
            Response::back('/pharmacy/products');
        }

        $db    = Database::instance();
        [$in, $inParams] = Database::inClause($productIds, 'prod');

        // Always constrained to the signed-in pharmacy.
        $scope = " AND pharmacy_id = :pharm";
        $inParams['pharm'] = (int) Auth::pharmacyId();

        $affected = match ($action) {
            'activate'   => $db->run("UPDATE products SET is_active = 1 WHERE id IN {$in}{$scope}", $inParams)->rowCount(),
            'deactivate' => $db->run("UPDATE products SET is_active = 0 WHERE id IN {$in}{$scope}", $inParams)->rowCount(),
            'feature'    => $db->run("UPDATE products SET is_featured = 1 WHERE id IN {$in}{$scope}", $inParams)->rowCount(),
            'unfeature'  => $db->run("UPDATE products SET is_featured = 0 WHERE id IN {$in}{$scope}", $inParams)->rowCount(),
            'delete'     => $db->run("UPDATE products SET deleted_at = NOW(), is_active = 0, is_visible = 0 WHERE id IN {$in}{$scope}", $inParams)->rowCount(),
            'stock'      => $this->bulkStock($request, $productIds, $inParams),
            'price'      => $this->bulkPrice($request, $productIds, $inParams),
            default      => 0,
        };

        if ($affected > 0) {
            (new AuditService())->log(
                'product.bulk_action',
                'product',
                null,
                sprintf('Bulk action "%s" applied to %d product(s)', $action, $affected)
            );
        }

        Session::success($affected > 0
            ? sprintf('%d product%s updated.', $affected, $affected === 1 ? '' : 's')
            : 'Nothing was changed.');

        Response::back('/pharmacy/products');
    }

    // -----------------------------------------------------------------------
    //  Bulk helpers
    // -----------------------------------------------------------------------

    private function bulkStock(Request $request, array $productIds, array $params): int
    {
        $mode   = (string) $request->input('stock_mode', 'set');
        $amount = (int) $request->input('stock_amount', 0);
        $db     = Database::instance();
        $inventory = new InventoryService();
        $count  = 0;

        foreach ($productIds as $productId) {
            $current = (int) $db->value(
                'SELECT stock_qty FROM products WHERE id = ? AND pharmacy_id = ?',
                ['id' => $productId, 'pharm' => $params['pharm']]
            );

            if ($current === 0 && $amount > 0) {
                continue;   // no row, or not ours
            }

            $newQty = match ($mode) {
                'increase' => $current + $amount,
                'decrease' => max(0, $current - $amount),
                default    => $amount,
            };

            if ($newQty === $current) {
                continue;
            }

            $inventory->adjust($productId, $newQty - $current, InventoryService::TYPE_ADJUSTMENT, 'Bulk stock update');
            $count++;
        }
        return $count;
    }

    private function bulkPrice(Request $request, array $productIds, array $params): int
    {
        $mode   = (string) $request->input('price_mode', 'set');
        $amount  = (float) $request->input('price_amount', 0);
        $db      = Database::instance();
        $updated = 0;

        foreach ($productIds as $productId) {
            $row = $db->first(
                'SELECT id, price FROM products WHERE id = ? AND pharmacy_id = ?',
                ['id' => $productId, 'pharm' => $params['pharm']]
            );
            if ($row === null) {
                continue;
            }

            $newPrice = match ($mode) {
                'increase' => round((float) $row['price'] + $amount, 2),
                'decrease' => round(max(0, (float) $row['price'] - $amount), 2),
                'percent'  => round((float) $row['price'] * (1 + $amount / 100), 2),
                default    => round($amount, 2),
            };

            if ($newPrice < 0) {
                continue;
            }

            $db->update('products', ['price' => $newPrice], 'id = ?', ['id' => $productId]);
            $updated++;
        }
        return $updated;
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function ownedProduct(int $id): array
    {
        $product = Database::instance()->first(
            'SELECT * FROM products WHERE id = ? AND pharmacy_id = ? AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'pharmacy_id' => Auth::pharmacyId()]
        );

        if ($product === null) {
            throw HttpException::notFound('That product was not found in your catalogue.');
        }
        return $product;
    }

    /** @return list<array<string,mixed>> */
    private function categoryTree(): array
    {
        return Database::instance()->all(
            'SELECT id, name, slug, parent_id FROM categories
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY parent_id IS NULL DESC, sort_order ASC, name ASC'
        );
    }

    /** @return list<array<string,mixed>> */
    private function brands(): array
    {
        return Database::instance()->all('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC');
    }

    private function uniqueSku(string $sku): string
    {
        $db  = Database::instance();
        $sku = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $sku) ?: 'SKU');
        $base = $sku;
        $n = 2;

        while ($db->value(
            'SELECT id FROM products WHERE pharmacy_id = ? AND sku = ?',
            ['pharmacy_id' => Auth::pharmacyId(), 'sku' => $sku]
        ) !== null) {
            $sku = $base . '-' . $n++;
        }
        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $db   = Database::instance();
        $base = slugify_text($name);
        $slug = $base;
        $n    = 2;

        while ($db->value('SELECT id FROM products WHERE slug = ?', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    /** Persist uploaded gallery images, keeping the first as primary. */
    private function saveImages(int $productId, Request $request, array $data): void
    {
        $db = Database::instance();

        $existing = (int) $db->value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', ['product_id' => $productId]);
        $order    = $existing;

        $single = $request->file('image');
        if ($single !== null) {
            $saved = Upload::store($single, 'product');
            if ($saved !== null) {
                $db->insert('product_images', [
                    'product_id' => $productId,
                    'file_path'  => $saved['path'],
                    'is_primary' => $existing === 0 ? 1 : 0,
                    'sort_order' => $order++,
                ]);
            }
        }

        foreach ($request->array('gallery') as $file) {
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saved = Upload::store($file, 'product');
            if ($saved !== null) {
                $db->insert('product_images', [
                    'product_id' => $productId,
                    'file_path'  => $saved['path'],
                    'is_primary' => $existing === 0 ? 1 : 0,
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    /** Log the opening balance as a purchase movement for the audit trail. */
    private function recordOpeningStock(int $productId, int $quantity, array $data): void
    {
        if ($quantity <= 0) {
            return;
        }

        (new InventoryService())->adjust(
            $productId,
            $quantity,
            InventoryService::TYPE_PURCHASE,
            'Opening stock',
            null,
            'product',
            $productId
        );
    }
}
