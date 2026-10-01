<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Session;
use App\Upload;
use App\View;

/**
 * Admin catalogue management: moderation of every product on the platform,
 * plus the category and brand taxonomies.
 */
final class ProductAdminController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /admin/products
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $db     = Database::instance();
        $search = trim((string) $request->query('q', ''));

        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        $pharmacyId = $request->queryInt('pharmacy', 0);
        if ($pharmacyId > 0) {
            $where[]           = 'p.pharmacy_id = :pharm';
            $params['pharm']  = $pharmacyId;
        }
        $categoryId = $request->queryInt('category', 0);
        if ($categoryId > 0) {
            $where[]          = 'p.category_id = :cat';
            $params['cat']   = $categoryId;
        }
        $status = (string) $request->query('status', '');
        if ($status === 'awaiting') {
            $where[] = 'p.is_approved = 0';
        } elseif ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        } elseif ($status === 'out_of_stock') {
            $where[] = 'p.stock_qty = 0';
        } elseif ($status === 'expired') {
            $where[] = 'p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()';
        }

        if ($search !== '') {
            $where[]      = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.generic_name LIKE :q3 OR p.brand_name LIKE :q4)';
            $like         = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM products p WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT p.*, ph.name AS pharmacy_name, c.name AS category_name,
                            (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                              ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
                     FROM products p
                     JOIN pharmacies ph ON ph.id = p.pharmacy_id
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE {$clause}
                     ORDER BY p.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(25),
            $this->page()
        );

        $this->view('admin/products/index', [
            'title'     => 'Products',
            'heading'   => 'All products',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'paginator' => $paginator,
            'search'    => $search,
            'status'    => $status,
            'pharmacyId' => $pharmacyId,
            'categoryId' => $categoryId,
            'pharmacies' => $db->all('SELECT id, name FROM pharmacies WHERE deleted_at IS NULL ORDER BY name ASC'),
            'categories' => $db->all('SELECT id, name FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL ORDER BY name ASC'),
            'stats'     => $db->first(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(is_approved = 0), 0) AS awaiting,
                        COALESCE(SUM(is_active = 1), 0) AS active,
                        COALESCE(SUM(stock_qty = 0), 0) AS out_of_stock,
                        COALESCE(SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired
                 FROM products WHERE deleted_at IS NULL'
            ) ?? [],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/products/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $product = $this->product((int) $this->param('id', $params));
        $db      = Database::instance();

        $this->view('admin/products/show', [
            'title'     => (string) $product['name'],
            'heading'   => (string) $product['name'],
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'product'   => $product,
            'pharmacy'  => $db->first('SELECT * FROM pharmacies WHERE id = ?', ['id' => $product['pharmacy_id']]),
            'images'    => $db->all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC', ['product_id' => $product['id']]),
            'movements' => $db->all(
                'SELECT m.*, u.full_name AS user_name FROM inventory_movements m
                 LEFT JOIN users u ON u.id = m.user_id
                 WHERE m.product_id = ? ORDER BY m.id DESC LIMIT 40',
                ['product_id' => $product['id']]
            ),
            'sales'     => $db->first(
                "SELECT COALESCE(SUM(oi.quantity), 0) AS units, COALESCE(SUM(oi.line_total), 0) AS revenue
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 WHERE oi.product_id = ? AND o.status NOT IN ('cancelled','refunded')",
                ['product_id' => $product['id']]
            ) ?? ['units' => 0, 'revenue' => 0],
            'categories' => $db->all('SELECT id, name FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL ORDER BY name ASC'),
            'breadcrumbs' => [['label' => 'Products', 'url' => '/admin/products'], ['label' => (string) $product['name']]],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/products/{id}
    // -----------------------------------------------------------------------
    public function update(Request $request, array $params): void
    {
        $product = $this->product((int) $this->param('id', $params));

        $data = $this->validate(
            [
                'name'         => 'required|string|min:3|max:200',
                'price'        => 'required|decimal|min:0',
                'discount_price' => 'nullable|decimal|min:0',
                'stock_qty'    => 'required|integer|min:0',
                'category_id'  => 'nullable|exists:categories,id',
                'product_class' => 'required|in:otc,prescription,restricted,device,supplement,cosmetic',
            ],
            $request->all(),
            'admin/products/show',
            '/admin/products/' . (int) $product['id']
        );

        $db = Database::instance();
        $db->update('products', [
            'name'         => $data['name'],
            'price'        => round((float) $data['price'], 2),
            'discount_price' => isset($data['discount_price']) && (float) $data['discount_price'] > 0
                && (float) $data['discount_price'] < (float) $data['price']
                ? round((float) $data['discount_price'], 2) : null,
            'stock_qty'    => (int) $data['stock_qty'],
            'category_id'  => $data['category_id'] ?? null,
            'product_class' => $data['product_class'],
            'is_active'    => $request->bool('is_active') ? 1 : 0,
            'is_visible'   => $request->bool('is_visible') ? 1 : 0,
            'is_approved'  => $request->bool('is_approved') ? 1 : 0,
            'requires_prescription' => $request->bool('requires_prescription') ? 1 : 0,
        ], 'id = ?', ['id' => $product['id']]);

        if ($request->file('image') !== null) {
            $saved = Upload::store($request->file('image'), 'product');
            if ($saved !== null) {
                $db->insert('product_images', [
                    'product_id' => $product['id'],
                    'file_path'  => $saved['path'],
                    'is_primary' => 0,
                ]);
            }
        }

        (new AuditService())->log('admin.product_updated', 'product', (int) $product['id'], sprintf(
            'Admin edited "%s"',
            $data['name']
        ));

        Session::success('Product updated.');
        Response::redirect('/admin/products/' . (int) $product['id']);
    }

    // -----------------------------------------------------------------------
    //  POST /admin/products/{id}/delete
    // -----------------------------------------------------------------------
    public function destroy(Request $request, array $params): void
    {
        $product = $this->product((int) $this->param('id', $params));

        Database::instance()->update('products', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active'  => 0,
            'is_visible' => 0,
        ], 'id = ?', ['id' => $product['id']]);

        (new AuditService())->log(
            'admin.product_removed',
            'product',
            (int) $product['id'],
            sprintf('Admin removed "%s" from the platform', (string) $product['name'])
        );

        Session::success('Product removed from the platform.');
        Response::redirect('/admin/products');
    }

    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function product(int $id): array
    {
        $product = Database::instance()->first('SELECT * FROM products WHERE id = ?', ['id' => $id]);
        if ($product === null) {
            throw HttpException::notFound('That product was not found.');
        }
        return $product;
    }
}
