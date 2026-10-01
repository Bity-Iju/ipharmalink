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
 * Admin-managed taxonomy: categories (with subcategories) and brands.
 * Nothing about the catalogue is hard-coded — this is the only place the
 * taxonomy changes.
 */
final class CategoryAdminController extends Controller
{
    // =======================================================================
    //  CATEGORIES
    // =======================================================================

    // -----------------------------------------------------------------------
    //  GET /admin/categories
    // -----------------------------------------------------------------------
    public function categories(Request $request): void
    {
        $db = Database::instance();

        $tree = $db->all(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
             FROM categories c WHERE c.parent_id IS NULL AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        foreach ($tree as &$category) {
            $category['children'] = $db->all(
                'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
                 FROM categories c WHERE c.parent_id = ? AND c.deleted_at IS NULL
                 ORDER BY c.sort_order ASC, c.name ASC',
                ['id' => $category['id']]
            );
        }
        unset($category);

        $this->view('admin/categories', [
            'title'     => 'Categories',
            'heading'   => 'Product categories',
            'sidebar'   => View::capture('admin/partials/sidebar'),
            'categories'=> $tree,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/categories
    // -----------------------------------------------------------------------
    public function storeCategory(Request $request): void
    {
        $data = $this->validate(
            [
                'name'        => 'required|string|min:2|max:120',
                'parent_id'   => 'nullable|exists:categories,id',
                'description' => 'nullable|string|max:1000',
                'icon'        => 'nullable|string|max:60',
                'sort_order'  => 'nullable|integer|min:0',
            ],
            $request->all(),
            'admin/categories',
            '/admin/categories'
        );

        $db = Database::instance();

        // A subcategory may not nest more than one level deep.
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId !== null) {
            $parent = $db->first('SELECT parent_id FROM categories WHERE id = ?', ['id' => $parentId]);
            if ($parent === null || $parent['parent_id'] !== null) {
                Session::error('Categories can only be nested one level deep.');
                Response::redirect('/admin/categories');
            }
        }

        $id = $db->insert('categories', [
            'name'        => $data['name'],
            'slug'        => $this->uniqueSlug((string) $data['name']),
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'parent_id'   => $parentId,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active') === false ? 1 : 0,
        ]);

        if ($request->file('image') !== null) {
            $saved = Upload::store($request->file('image'), 'category');
            if ($saved !== null) {
                $db->update('categories', ['image' => $saved['path']], 'id = ?', ['id' => $id]);
            }
        }

        (new AuditService())->log('admin.category_created', 'category', $id, 'Category created: ' . $data['name']);

        Session::success('Category created.');
        Response::redirect('/admin/categories');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/categories/{id}
    // -----------------------------------------------------------------------
    public function updateCategory(Request $request, array $params): void
    {
        $id       = (int) $this->param('id', $params);
        $category = $this->category($id);

        $data = $this->validate(
            [
                'name'        => 'required|string|min:2|max:120',
                'description' => 'nullable|string|max:1000',
                'icon'        => 'nullable|string|max:60',
                'sort_order'  => 'nullable|integer|min:0',
            ],
            $request->all(),
            'admin/categories',
            '/admin/categories'
        );

        $db = Database::instance();
        $db->update('categories', [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active') ? 1 : 0,
        ], 'id = ?', ['id' => $id]);

        if ($request->file('image') !== null) {
            $saved = Upload::store($request->file('image'), 'category');
            if ($saved !== null) {
                $db->update('categories', ['image' => $saved['path']], 'id = ?', ['id' => $id]);
            }
        }

        (new AuditService())->log('admin.category_updated', 'category', $id, 'Category updated: ' . $data['name']);

        Session::success('Category updated.');
        Response::redirect('/admin/categories');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/categories/{id}/delete
    // -----------------------------------------------------------------------
    public function destroyCategory(Request $request, array $params): void
    {
        $id       = (int) $this->param('id', $params);
        $category = $this->category($id);
        $db       = Database::instance();

        $productCount = (int) $db->value(
            'SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL',
            ['category_id' => $id]
        );

        if ($productCount > 0) {
            Session::error(sprintf(
                'Cannot delete "%s" — %d product%s still use it. Move them first, or deactivate the category instead.',
                (string) $category['name'],
                $productCount,
                $productCount === 1 ? '' : 's'
            ));
            Response::redirect('/admin/categories');
        }

        // Re-parent any subcategories to the top level rather than orphaning.
        $db->update('categories', ['parent_id' => null], 'parent_id = ?', ['parent_id' => $id]);

        $db->update('categories', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active'  => 0,
        ], 'id = ?', ['id' => $id]);

        (new AuditService())->log('admin.category_deleted', 'category', $id, 'Category deleted: ' . (string) $category['name']);

        Session::success('Category deleted.');
        Response::redirect('/admin/categories');
    }

    // =======================================================================
    //  BRANDS
    // =======================================================================

    // -----------------------------------------------------------------------
    //  GET /admin/brands
    // -----------------------------------------------------------------------
    public function brands(Request $request): void
    {
        $db = Database::instance();

        $brands = $db->all(
            'SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS product_count
             FROM brands b ORDER BY b.name ASC LIMIT 500'
        );

        $this->view('admin/brands', [
            'title'   => 'Brands',
            'heading' => 'Brands',
            'sidebar' => View::capture('admin/partials/sidebar'),
            'brands'  => $brands,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/brands
    // -----------------------------------------------------------------------
    public function storeBrand(Request $request): void
    {
        $data = $this->validate(
            ['name' => 'required|string|min:2|max:120'],
            $request->all(),
            'admin/brands',
            '/admin/brands'
        );

        $db = Database::instance();
        $name = (string) $data['name'];

        if ($db->value('SELECT id FROM brands WHERE name = ?', ['name' => $name]) !== null) {
            Session::error('That brand already exists.');
            Response::redirect('/admin/brands');
        }

        $id = $db->insert('brands', [
            'name'      => $name,
            'slug'      => $this->uniqueSlug($name),
            'is_active' => 1,
        ]);

        if ($request->file('logo') !== null) {
            $saved = Upload::store($request->file('logo'), 'brand');
            if ($saved !== null) {
                $db->update('brands', ['logo' => $saved['path']], 'id = ?', ['id' => $id]);
            }
        }

        (new AuditService())->log('admin.brand_created', 'brand', $id, 'Brand created: ' . $name);

        Session::success('Brand created.');
        Response::redirect('/admin/brands');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/brands/{id}
    // -----------------------------------------------------------------------
    public function updateBrand(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $brand = $this->brand($id);

        $data = $this->validate(
            ['name' => 'required|string|min:2|max:120'],
            $request->all(),
            'admin/brands',
            '/admin/brands'
        );

        $db = Database::instance();
        $db->update('brands', [
            'name'      => $data['name'],
            'is_active' => $request->bool('is_active') ? 1 : 0,
        ], 'id = ?', ['id' => $id]);

        if ($request->file('logo') !== null) {
            $saved = Upload::store($request->file('logo'), 'brand');
            if ($saved !== null) {
                $db->update('brands', ['logo' => $saved['path']], 'id = ?', ['id' => $id]);
            }
        }

        (new AuditService())->log('admin.brand_updated', 'brand', $id, 'Brand updated: ' . $data['name']);

        Session::success('Brand updated.');
        Response::redirect('/admin/brands');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/brands/{id}/delete
    // -----------------------------------------------------------------------
    public function destroyBrand(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $brand = $this->brand($id);
        $db    = Database::instance();

        $productCount = (int) $db->value(
            'SELECT COUNT(*) FROM products WHERE brand_id = ? AND deleted_at IS NULL',
            ['brand_id' => $id]
        );

        if ($productCount > 0) {
            $db->update('brands', ['is_active' => 0], 'id = ?', ['id' => $id]);
            Session::warning(sprintf(
                '"%s" is used by %d product(s), so it was deactivated rather than deleted.',
                (string) $brand['name'],
                $productCount
            ));
            Response::redirect('/admin/brands');
        }

        $db->delete('brands', 'id = ?', ['id' => $id]);

        (new AuditService())->log('admin.brand_deleted', 'brand', $id, 'Brand deleted: ' . (string) $brand['name']);

        Session::success('Brand deleted.');
        Response::redirect('/admin/brands');
    }

    // =======================================================================

    /** @return array<string,mixed> */
    private function category(int $id): array
    {
        $category = Database::instance()->first('SELECT * FROM categories WHERE id = ?', ['id' => $id]);
        if ($category === null) {
            throw HttpException::notFound('That category was not found.');
        }
        return $category;
    }

    /** @return array<string,mixed> */
    private function brand(int $id): array
    {
        $brand = Database::instance()->first('SELECT * FROM brands WHERE id = ?', ['id' => $id]);
        if ($brand === null) {
            throw HttpException::notFound('That brand was not found.');
        }
        return $brand;
    }

    private function uniqueSlug(string $name): string
    {
        $db   = Database::instance();
        $base = slugify_text($name);
        $slug = $base;
        $n    = 2;

        while ($db->value('SELECT id FROM categories WHERE slug = ?', ['slug' => $slug]) !== null
            || $db->value('SELECT id FROM brands WHERE slug = ?', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}
