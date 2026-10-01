<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Paginator;

/**
 * iPharmaLink :: Catalog query service
 * ---------------------------------------------------------------------------
 * The one place that knows how to search and filter the product catalogue.
 * The storefront, the pharmacy store page and the JSON API all call in here
 * so filters, sorting and availability rules can never drift apart.
 *
 * Every user-supplied value is bound as a PDO parameter. The only values
 * ever interpolated into SQL are column names, and those pass through
 * Database::safeColumn() which whitelists them.
 */
final class CatalogService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    // -----------------------------------------------------------------------
    //  Public listing
    // -----------------------------------------------------------------------

    /**
     * Search and filter products.
     *
     * @param  array<string,mixed> $filters
     *         q, category, subcategory, pharmacy, brand, min_price, max_price,
     *         in_stock, prescription, sort, per_page, page
     * @return \App\Paginator
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 24): Paginator
    {
        [$where, $params] = $this->buildFilters($filters);
        $order = $this->buildOrder((string) ($filters['sort'] ?? 'relevance'), $params);
        $perPage = max(1, min(60, $perPage));

        // The relevance ORDER BY introduces placeholders that exist only in
        // the rows query. The COUNT query must not receive them.
        $countParams = $params;
        unset($countParams['exact'], $countParams['exact_name'], $countParams['exact_generic'], $countParams['exact_brand']);

        return Paginator::build(
            fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(DISTINCT p.id) FROM products p
                 JOIN pharmacies ph ON ph.id = p.pharmacy_id
                 ' . $this->joins($params) . '
                 WHERE ' . $where,
                $countParams
            ),
            function (Database $db, int $perPage, int $offset) use ($where, $params, $order): array {
                return $db->all(
                    'SELECT DISTINCT ' . $this->columns() . '
                     FROM products p
                     JOIN pharmacies ph ON ph.id = p.pharmacy_id
                     ' . $this->joins($params) . '
                     WHERE ' . $where . '
                     ORDER BY ' . $order . '
                     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                    $params
                );
            },
            $perPage,
            $page
        );
    }

    /** Facet data that drives the filter panel. */
    public function filterOptions(): array
    {
        return [
            'categories' => $this->db->all(
                'SELECT c.id, c.name, c.slug, c.parent_id,
                        (SELECT COUNT(*) FROM products p2 WHERE p2.category_id = c.id AND p2.is_active = 1 AND p2.deleted_at IS NULL) AS product_count
                 FROM categories c
                 WHERE c.is_active = 1 AND c.deleted_at IS NULL
                 ORDER BY c.parent_id IS NULL DESC, c.sort_order ASC, c.name ASC'
            ),
            'pharmacies' => $this->db->all(
                "SELECT id, name, slug, city FROM pharmacies
                 WHERE status = 'approved' AND deleted_at IS NULL ORDER BY name ASC"
            ),
            'brands' => $this->db->all(
                'SELECT b.id, b.name, b.slug
                 FROM brands b
                 WHERE b.is_active = 1
                   AND EXISTS (SELECT 1 FROM products p WHERE p.brand_id = b.id AND p.is_active = 1 AND p.deleted_at IS NULL)
                 ORDER BY b.name ASC LIMIT 120'
            ),
            'priceRange' => [
                'min' => (float) ($this->db->value('SELECT MIN(price) FROM products WHERE is_active = 1 AND deleted_at IS NULL') ?? 0),
                'max' => (float) ($this->db->value('SELECT MAX(price) FROM products WHERE is_active = 1 AND deleted_at IS NULL') ?? 0),
            ],
        ];
    }

    // -----------------------------------------------------------------------
    //  Single product
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $product = $this->db->first(
            'SELECT p.*, ph.name AS pharmacy_name, ph.slug AS pharmacy_slug, ph.logo AS pharmacy_logo,
                    ph.phone AS pharmacy_phone, ph.email AS pharmacy_email, ph.city AS pharmacy_city,
                    ph.state AS pharmacy_state, ph.address AS pharmacy_address,
                    ph.rating_avg AS pharmacy_rating, ph.rating_count AS pharmacy_rating_count,
                    c.name AS category_name, c.slug AS category_slug,
                    b.name AS brand_resolved
             FROM products p
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.slug = ? AND p.deleted_at IS NULL LIMIT 1',
            ['slug' => $slug]
        );

        if ($product === null) {
            return null;
        }

        $product['images'] = $this->db->all(
            'SELECT id, file_path, is_primary FROM product_images
             WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC',
            ['product_id' => $product['id']]
        );
        if ($product['images'] === []) {
            $product['images'][] = ['id' => 0, 'file_path' => $product['image'] ?? null, 'is_primary' => 1];
        }
        $product['primary_image'] = $product['images'][0]['file_path'] ?? null;

        $product['rating_breakdown'] = $this->db->all(
            'SELECT rating, COUNT(*) AS total FROM reviews
             WHERE product_id = ? AND status = "published" GROUP BY rating',
            ['product_id' => $product['id']]
        );
        $product['reviews'] = $this->db->all(
            'SELECT r.id, r.rating, r.title, r.body, r.created_at, u.full_name AS author
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND r.status = "published"
             ORDER BY r.id DESC LIMIT 10',
            ['product_id' => $product['id']]
        );

        return $product;
    }

    /** Bump the view counter, fire-and-forget. */
    public function recordView(int $productId): void
    {
        try {
            $this->db->run('UPDATE products SET views = views + 1 WHERE id = ?', ['id' => $productId]);
        } catch (\Throwable) {
            // A view count is never worth failing a page load for.
        }
    }

    /** Products a customer is likely to also buy. */
    public function related(int $productId, int $categoryId, int $limit = 8): array
    {
        return $this->db->all(
            'SELECT p.id, p.slug, p.name, p.brand_name, p.strength, p.price, p.discount_price,
                    p.stock_qty, p.requires_prescription, ph.name AS pharmacy_name,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE p.is_active = 1 AND p.is_visible = 1 AND p.deleted_at IS NULL
               AND ph.status = "approved" AND p.id <> ?
               AND (p.category_id = ? OR p.brand_id = (SELECT brand_id FROM products WHERE id = ?))
             ORDER BY (p.category_id = ?) DESC, p.sales_count DESC
             LIMIT ' . (int) $limit,
            ['id' => $productId, 'cat' => $categoryId, 'id2' => $productId, 'cat2' => $categoryId]
        );
    }

    /** In-stock availability across every pharmacy selling this product. */
    public function otherSellers(int $productId, int $limit = 6): array
    {
        return $this->db->all(
            'SELECT p.id, p.slug, p.price, p.discount_price, p.stock_qty, p.expiry_date,
                    ph.name AS pharmacy_name, ph.slug AS pharmacy_slug, ph.city, ph.delivery_fee,
                    ph.estimated_delivery_minutes
             FROM products p
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE p.is_active = 1 AND p.stock_qty > 0 AND p.deleted_at IS NULL
               AND ph.status = "approved" AND p.id <> ?
               AND (p.generic_name = (SELECT generic_name FROM products WHERE id = ?)
                    OR p.name = (SELECT name FROM products WHERE id = ?))
             ORDER BY (p.discount_price IS NOT NULL AND p.discount_price < p.price) DESC, p.price ASC
             LIMIT ' . (int) $limit,
            ['id' => $productId, 'id2' => $productId, 'id3' => $productId]
        );
    }

    // -----------------------------------------------------------------------
    //  Typeahead
    // -----------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function suggest(string $term, int $limit = 8): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        return $this->db->all(
            'SELECT p.id, p.slug, p.name, p.brand_name, p.price, p.discount_price, ph.name AS pharmacy_name,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE p.is_active = 1 AND p.is_visible = 1 AND p.deleted_at IS NULL
               AND ph.status = "approved"
               AND (p.name LIKE :a OR p.generic_name LIKE :b OR p.brand_name LIKE :c
                    OR p.active_ingredient LIKE :d)
             ORDER BY p.sales_count DESC LIMIT ' . (int) $limit,
            ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like]
        );
    }

    // -----------------------------------------------------------------------
    //  Filter plumbing
    // -----------------------------------------------------------------------

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildFilters(array $filters): array
    {
        $where  = [
            'p.deleted_at IS NULL',
            'p.is_active = 1',
            'p.is_visible = 1',
            'p.is_approved = 1',
            "ph.status = 'approved'",
            'ph.deleted_at IS NULL'
        ];
        $params = [];

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            // Prefix matches rank above substring matches in the ORDER BY.
            $where[] = '(p.name LIKE :q1 OR p.generic_name LIKE :q2 OR p.brand_name LIKE :q3
                         OR p.active_ingredient LIKE :q4 OR p.description LIKE :q5
                         OR ph.name LIKE :q6 OR c.name LIKE :q7)';
            $params['q1']  = $like;
            $params['q2']  = $like;
            $params['q3']  = $like;
            $params['q4']  = $like;
            $params['q5']  = $like;
            $params['q6']  = $like;
            $params['q7']  = $like;
        }

        // A category filter also covers products filed under its subcategories.
        if (!empty($filters['category'])) {
            $ids = $this->categoryTree((int) $filters['category']);
            [$in, $inParams] = Database::inClause($ids, 'cat');
            $where[]  = "(p.category_id IN {$in} OR p.subcategory_id IN {$in})";
            $params   = array_merge($params, $inParams, $inParams);
        }

        if (!empty($filters['pharmacy'])) {
            $where[]            = 'p.pharmacy_id = :pharmacy';
            $params['pharmacy'] = (int) $filters['pharmacy'];
        }

        if (!empty($filters['brand'])) {
            $where[]        = 'p.brand_id = :brand';
            $params['brand'] = (int) $filters['brand'];
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '' && $filters['min_price'] !== null) {
            $where[]           = 'COALESCE(NULLIF(p.discount_price, 0), p.price) >= :min_price';
            $params['min_price'] = (float) $filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '' && $filters['max_price'] !== null) {
            $where[]           = 'COALESCE(NULLIF(p.discount_price, 0), p.price) <= :max_price';
            $params['max_price'] = (float) $filters['max_price'];
        }

        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock_qty > 0';
        }
        if (array_key_exists('prescription', $filters) && $filters['prescription'] !== '' && $filters['prescription'] !== null) {
            if ((string) $filters['prescription'] === '1') {
                $where[] = 'p.requires_prescription = 1';
            } elseif ((string) $filters['prescription'] === '0') {
                $where[] = 'p.requires_prescription = 0';
            }
        }
        if (!empty($filters['class'])) {
            $allowed = ['otc', 'prescription', 'restricted', 'device', 'supplement', 'cosmetic'];
            $class   = in_array((string) $filters['class'], $allowed, true) ? (string) $filters['class'] : null;
            if ($class !== null) {
                $where[]          = 'p.product_class = :class';
                $params['class']  = $class;
            }
        }

        // Never surface an expired product.
        $where[] = '(p.expiry_date IS NULL OR p.expiry_date >= CURDATE())';

        return [implode(' AND ', $where), $params];
    }

    /**
     * Extra JOINs required by the active filters.
     *
     * @param array<string,mixed> $params bound parameters, used to decide joins
     */
    private function joins(array $params): string
    {
        // The relevance ORDER BY references c.name, so the join is required
        // whenever a search term is present.
        return array_key_exists('q1', $params) ? ' LEFT JOIN categories c ON c.id = p.category_id' : '';
    }

    /**
     * Whitelisted ORDER BY. When a search term is present, $params also gains
     * the :exact bound values the relevance ranking needs.
     *
     * @param  array<string,mixed> $params
     */
    private function buildOrder(string $sort, array &$params): string
    {
        $hasQuery = array_key_exists('q1', $params);

        if ($hasQuery && ($sort === 'relevance' || $sort === '')) {
            $term = trim((string) $params['q1'], '%');
            // A named placeholder may only appear once per statement when
            // emulation is off, so the ranking gets its own aliases.
            $params['exact_name']    = $term . '%';
            $params['exact_generic'] = $term . '%';
            $params['exact_brand']   = $term . '%';
            unset($params['exact']);
        }

        return match ($sort) {
            'price_asc'      => 'COALESCE(NULLIF(p.discount_price, 0), p.price) ASC, p.id ASC',
            'price_desc'     => 'COALESCE(NULLIF(p.discount_price, 0), p.price) DESC, p.id DESC',
            'newest'         => 'p.created_at DESC, p.id DESC',
            'popularity'     => 'p.sales_count DESC, p.rating_avg DESC, p.id DESC',
            'rating'         => 'p.rating_avg DESC, p.rating_count DESC',
            'name'           => 'p.name ASC',
            default          => $hasQuery
                ? '(CASE WHEN p.name LIKE :exact_name THEN 0
                          WHEN p.generic_name LIKE :exact_generic THEN 1
                          WHEN p.brand_name LIKE :exact_brand THEN 2 ELSE 3 END) ASC,
                   p.sales_count DESC, p.rating_avg DESC, p.id ASC'
                : 'p.is_featured DESC, p.sales_count DESC, p.rating_avg DESC, p.id ASC',
        };
    }

    /**
     * A category plus every descendant (subcategories share the table).
     *
     * @return list<int>
     */
    public function categoryTree(int $categoryId): array
    {
        $all       = [$categoryId];
        $frontier  = [$categoryId];
        $guard     = 0;

        while ($frontier !== [] && $guard++ < 4) {
            [$in, $params] = Database::inClause($frontier, 'tree');
            $children = array_map('intval', $this->db->column(
                "SELECT id FROM categories WHERE parent_id IN {$in} AND deleted_at IS NULL",
                $params
            ));
            $frontier = array_values(array_diff($children, $all));
            $all      = array_merge($all, $frontier);
        }
        return $all;
    }

    private function columns(): string
    {
        return 'p.id, p.slug, p.name, p.generic_name, p.brand_name, p.strength, p.dosage_form,
                p.pack_size, p.requires_prescription, p.product_class, p.price, p.discount_price,
                p.stock_qty, p.min_stock_level, p.rating_avg, p.rating_count, p.sales_count,
                p.expiry_date, p.manufacturer, p.category_id, p.subcategory_id, p.pharmacy_id,
                ph.name AS pharmacy_name, ph.slug AS pharmacy_slug, ph.city AS pharmacy_city,
                ph.state AS pharmacy_state, ph.rating_avg AS pharmacy_rating,
                (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                  ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image';
    }
}
