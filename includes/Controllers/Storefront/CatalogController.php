<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Services\CatalogService;
use App\Session;
use App\Setting;

/**
 * Catalogue browsing: product list, product detail, categories, brand pages,
 * search and deals. All listing routes share one filter contract.
 */
final class CatalogController extends Controller
{
    private CatalogService $catalog;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->catalog = new CatalogService();
    }

    // -----------------------------------------------------------------------
    //  /products
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $this->renderListing(
            $request,
            'Browse all products',
            'Every medicine, supplement, medical device and health product available from verified pharmacies on '
                . Setting::getString('general.platform_name', 'iPharmaLink') . '.',
            []
        );
    }

    // -----------------------------------------------------------------------
    //  /search?q=
    // -----------------------------------------------------------------------
    public function search(Request $request): void
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            $this->renderListing($request, 'Search', 'Search medicines and health products.', []);
            return;
        }

        $this->renderListing(
            $request,
            sprintf('Search results for “%s”', $term),
            sprintf('%d results for %s across verified pharmacies.', 0, $term),
            ['q' => $term]
        );
    }

    // -----------------------------------------------------------------------
    //  /deals
    // -----------------------------------------------------------------------
    public function deals(Request $request): void
    {
        $this->renderListing(
            $request,
            'Today’s deals and discounts',
            'Discounted medicines and health products from pharmacies on ' . Setting::getString('general.platform_name', 'iPharmaLink') . '.',
            ['deals_only' => true]
        );
    }

    // -----------------------------------------------------------------------
    //  /categories
    // -----------------------------------------------------------------------
    public function categories(Request $request): void
    {
        $db = Database::instance();

        $tree = $db->all(
            'SELECT c.id, c.name, c.slug, c.description, c.icon, c.image,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.is_active = 1 AND p.is_visible = 1
                        AND p.deleted_at IS NULL AND p.stock_qty > 0) AS product_count
             FROM categories c
             WHERE c.parent_id IS NULL AND c.is_active = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        foreach ($tree as &$category) {
            $category['children'] = $db->all(
                'SELECT id, name, slug FROM categories
                 WHERE parent_id = ? AND is_active = 1 AND deleted_at IS NULL
                 ORDER BY sort_order ASC, name ASC',
                ['id' => $category['id']]
            );
        }
        unset($category);

        $this->view('storefront/categories', [
            'title'           => 'Product categories',
            'metaDescription' => 'Browse medicines and health products by category — prescription, OTC, pain relief, vitamins, baby care and more.',
            'categories'      => $tree,
        ]);
    }

    // -----------------------------------------------------------------------
    //  /category/{slug}
    // -----------------------------------------------------------------------
    public function category(Request $request, array $params): void
    {
        $slug = $this->param('slug', $params);
        $db   = Database::instance();

        $category = $db->first(
            'SELECT * FROM categories WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            ['slug' => $slug]
        );
        if ($category === null) {
            throw HttpException::notFound('We could not find that category.');
        }

        $this->renderListing(
            $request,
            $category['name'],
            str_excerpt((string) $category['description'], 155)
                ?: sprintf('Shop %s from verified pharmacies with delivery or pickup.', $category['name']),
            ['category' => (int) $category['id']],
            $category
        );
    }

    // -----------------------------------------------------------------------
    //  /brand/{slug}
    // -----------------------------------------------------------------------
    public function brand(Request $request, array $params): void
    {
        $slug = $this->param('slug', $params);

        $brand = Database::instance()->first(
            'SELECT * FROM brands WHERE slug = ? AND is_active = 1 LIMIT 1',
            ['slug' => $slug]
        );
        if ($brand === null) {
            throw HttpException::notFound('We could not find that brand.');
        }

        $this->renderListing(
            $request,
            $brand['name'] . ' products',
            sprintf('All %s products available from verified pharmacies.', $brand['name']),
            ['brand' => (int) $brand['id']],
            $brand
        );
    }

    // -----------------------------------------------------------------------
    //  /product/{slug}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $product = $this->catalog->findBySlug($this->param('slug', $params));
        if ($product === null) {
            throw HttpException::notFound('That product is no longer available.');
        }

        // Guests and unapproved pharmacies never see the storefront.
        $storefrontVisible = (int) $product['is_visible'] === 1
            && (int) $product['is_active'] === 1;

        $this->catalog->recordView((int) $product['id']);

        $inWishlist = false;
        if (\App\Auth::isCustomer()) {
            $inWishlist = Database::instance()->value(
                'SELECT wi.id FROM wishlist_items wi
                 JOIN wishlists w ON w.id = wi.wishlist_id
                 WHERE w.user_id = ? AND wi.product_id = ? LIMIT 1',
                ['user_id' => \App\Auth::id(), 'product_id' => $product['id']]
            ) !== null;
        }

        $price    = (float) $product['price'];
        $discount = isset($product['discount_price']) && (float) $product['discount_price'] > 0 && (float) $product['discount_price'] < $price
            ? (float) $product['discount_price'] : null;

        // Product structured data for rich results.
        $structuredData = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $product['name'],
            'sku'         => (string) $product['sku'],
            'description' => str_excerpt((string) $product['description'], 300),
            'image'       => upload_url($product['primary_image']),
            'brand'       => ['@type' => 'Brand', 'name' => (string) ($product['brand_name'] ?: $product['brand_resolved'] ?? 'Unbranded')],
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => number_format($discount ?? $price, 2, '.', ''),
                'priceCurrency' => Setting::getString('currency.currency_code', 'NGN'),
                'availability'  => (int) $product['stock_qty'] > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'seller'        => ['@type' => 'Organization', 'name' => (string) $product['pharmacy_name']],
            ],
        ];
        if ((float) $product['rating_count'] > 0) {
            $structuredData['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format((float) $product['rating_avg'], 1, '.', ''),
                'reviewCount' => (int) $product['rating_count'],
            ];
        }

        $scripts = '<script type="application/ld+json">'
            . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';

        $this->view('storefront/product', [
            'title'              => (string) $product['name'] . ' – ' . (string) $product['pharmacy_name'],
            'metaDescription'    => sprintf(
                'Buy %s%s from %s on %s. %s',
                (string) $product['name'],
                $product['strength'] ? ' ' . (string) $product['strength'] : '',
                (string) $product['pharmacy_name'],
                Setting::getString('general.platform_name', 'iPharmaLink'),
                (int) $product['stock_qty'] > 0 ? 'In stock with delivery available.' : 'Currently out of stock.'
            ),
            'og'                 => [
                'title' => (string) $product['name'],
                'image' => $product['primary_image'],
                'type'  => 'product',
                'url'   => url('/product/' . (string) $product['slug']),
            ],
            'product'            => $product,
            'price'              => $price,
            'discountPrice'      => $discount,
            'savePercent'        => $discount !== null ? (int) round((1 - $discount / $price) * 100) : 0,
            'storefrontVisible'  => $storefrontVisible,
            'inWishlist'         => $inWishlist,
            'related'            => $this->catalog->related((int) $product['id'], (int) ($product['category_id'] ?? 0)),
            'otherSellers'       => $this->catalog->otherSellers((int) $product['id']),
            'scripts'            => $scripts,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Shared listing renderer
    // -----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $seed  base filter values
     * @param array<string,mixed>|null $entity  category or brand, when applicable
     */
    private function renderListing(Request $request, string $title, string $description, array $seed, ?array $entity = null): void
    {
        $filters = [
            'q'           => trim((string) $request->query('q', $seed['q'] ?? '')),
            'category'    => $request->queryInt('category', (int) ($seed['category'] ?? 0)),
            'pharmacy'    => $request->queryInt('pharmacy', 0),
            'brand'       => $request->queryInt('brand', (int) ($seed['brand'] ?? 0)),
            'min_price'   => $request->query('min_price', ''),
            'max_price'   => $request->query('max_price', ''),
            'in_stock'    => $request->query('in_stock', ''),
            'prescription' => $request->query('prescription', ''),
            'class'       => $request->query('class', ''),
            'sort'        => $request->query('sort', 'relevance'),
        ];

        // "Deals" is a view over the same catalogue: only discounted, in stock.
        if (!empty($seed['deals_only'])) {
            $filters['deals_only'] = true;
        }

        $paginator = $this->catalog->search($filters, $this->page(), $this->perPage(24));

        $this->view('storefront/products', [
            'title'           => $title,
            'metaDescription' => $description,
            'paginator'       => $paginator,
            'filters'         => $filters,
            'options'         => $this->catalog->filterOptions(),
            'entity'          => $entity,
            'heading'         => $title,
            'resultCount'     => $paginator->total(),
        ]);
    }
}
