<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\Paginator;
use App\Request;
use App\Setting;

/**
 * Storefront homepage and SEO endpoints.
 */
final class HomeController extends Controller
{
    /** Homepage: hero, categories, banners, featured pharmacies, popular & new products. */
    public function index(Request $request): void
    {
        $db = Database::instance();

        $banners = $db->all(
            "SELECT title, subtitle, image, mobile_image, button_text, button_link
             FROM banners
             WHERE is_active = 1 AND placement = 'home_slider'
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order ASC LIMIT 5"
        );

        $categories = $db->all(
            'SELECT c.id, c.name, c.slug, c.icon,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.is_active = 1 AND p.is_visible = 1
                        AND p.deleted_at IS NULL AND p.stock_qty > 0) AS product_count
             FROM categories c
             WHERE c.parent_id IS NULL AND c.is_active = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC LIMIT 12'
        );

        $featuredPharmacies = $db->all(
            "SELECT id, name, slug, logo, city, state, rating_avg, rating_count,
                    delivery_available, pickup_available, delivery_fee, estimated_delivery_minutes
             FROM pharmacies
             WHERE status = 'approved' AND is_featured = 1 AND deleted_at IS NULL
             ORDER BY rating_avg DESC, name ASC LIMIT 8"
        );

        if ($featuredPharmacies === []) {
            $featuredPharmacies = $db->all(
                "SELECT id, name, slug, logo, city, state, rating_avg, rating_count,
                        delivery_available, pickup_available, delivery_fee, estimated_delivery_minutes
                 FROM pharmacies
                 WHERE status = 'approved' AND deleted_at IS NULL
                 ORDER BY rating_avg DESC, rating_count DESC, name ASC LIMIT 8"
            );
        }

        $imageSelect = '(SELECT pi.file_path FROM product_images pi
                          WHERE pi.product_id = p.id
                          ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image';

        $productColumns = "p.id, p.slug, p.name, p.brand_name, p.strength, p.price, p.discount_price,
                           p.stock_qty, p.requires_prescription, ph.name AS pharmacy_name, {$imageSelect}";

        $base = "FROM products p
                  JOIN pharmacies ph ON ph.id = p.pharmacy_id
                  WHERE p.is_active = 1 AND p.is_visible = 1 AND p.is_approved = 1
                    AND p.deleted_at IS NULL AND ph.status = 'approved'
                    AND ph.deleted_at IS NULL";

        $popular = $db->all(
            "SELECT {$productColumns} {$base} AND p.stock_qty > 0
             ORDER BY p.sales_count DESC, p.rating_avg DESC LIMIT 12"
        );

        $newest = $db->all(
            "SELECT {$productColumns} {$base}
             ORDER BY p.created_at DESC, p.id DESC LIMIT 12"
        );

        $deals = $db->all(
            "SELECT {$productColumns} {$base}
               AND p.discount_price IS NOT NULL AND p.discount_price > 0 AND p.discount_price < p.price
               AND p.stock_qty > 0
             ORDER BY (p.price - p.discount_price) DESC LIMIT 8"
        );

        $health = $db->all(
            "SELECT {$productColumns} {$base}
               AND p.product_class IN ('supplement','device','cosmetic')
             ORDER BY p.sales_count DESC LIMIT 8"
        );

        $this->view('storefront/home', [
            'title'              => Setting::getString('general.platform_name', 'iPharmaLink')
                . ' — Medicines delivered from verified pharmacies',
            'metaDescription'    => 'Buy genuine medicines, vitamins and health products from verified pharmacies. '
                . 'Choose home delivery or pharmacy pickup. Prescription items reviewed by licensed pharmacists.',
            'banners'            => $banners,
            'categories'         => $categories,
            'featuredPharmacies' => $featuredPharmacies,
            'popularProducts'    => $popular,
            'newProducts'        => $newest,
            'deals'              => $deals,
            'healthProducts'     => $health,
            'stats'              => [
                'pharmacies' => (int) $db->value("SELECT COUNT(*) FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL"),
                'products'   => (int) $db->value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL'),
                'orders'     => (int) $db->value("SELECT COUNT(*) FROM orders WHERE status = 'delivered'"),
            ],
        ]);
    }

    /** XML sitemap covering public content. */
    public function sitemap(Request $request): void
    {
        $db   = Database::instance();
        $base = rtrim(Setting::getString('app.url', (string) \App\Config::str('app.url')), '/');
        $now  = date('Y-m-d');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $statics = [
            ['/', 'daily', '1.0'],
            ['/products', 'daily', '0.9'],
            ['/pharmacies', 'daily', '0.9'],
            ['/categories', 'weekly', '0.8'],
            ['/deals', 'daily', '0.8'],
            ['/about', 'monthly', '0.5'],
            ['/contact', 'monthly', '0.5'],
            ['/faq', 'monthly', '0.5'],
            ['/terms', 'yearly', '0.3'],
            ['/privacy', 'yearly', '0.3'],
        ];
        foreach ($statics as [$path, $freq, $priority]) {
            printf(
                "  <url><loc>%s%s</loc><changefreq>%s</changefreq><priority>%s</priority></url>\n",
                $base,
                $path,
                $freq,
                $priority
            );
        }

        foreach ($db->all("SELECT slug, updated_at FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL") as $row) {
            printf(
                "  <url><loc>%s/pharmacy/%s</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n",
                $base,
                rawurlencode((string) $row['slug']),
                substr((string) $row['updated_at'], 0, 10)
            );
        }

        foreach ($db->all('SELECT slug, updated_at FROM products WHERE is_active = 1 AND is_visible = 1 AND deleted_at IS NULL') as $row) {
            printf(
                "  <url><loc>%s/product/%s</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>\n",
                $base,
                rawurlencode((string) $row['slug']),
                substr((string) $row['updated_at'], 0, 10)
            );
        }

        foreach ($db->all('SELECT slug FROM categories WHERE is_active = 1 AND deleted_at IS NULL') as $row) {
            printf(
                "  <url><loc>%s/category/%s</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>\n",
                $base,
                rawurlencode((string) $row['slug'])
            );
        }

        echo '</urlset>';
        exit;
    }

    /** robots.txt served from PHP so the sitemap URL always matches APP_URL. */
    public function robots(Request $request): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $base = rtrim(Setting::getString('app.url', (string) \App\Config::str('app.url')), '/');

        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /account\n";
        echo "Disallow: /cart\n";
        echo "Disallow: /checkout\n";
        echo "Disallow: /payment\n";
        echo "Disallow: /pharmacy/dashboard\n";
        echo "Disallow: /pharmacy/products\n";
        echo "Disallow: /pharmacy/orders\n";
        echo "Disallow: /delivery\n";
        echo "Disallow: /api\n";
        echo "\nSitemap: {$base}/sitemap.xml\n";
        exit;
    }
}
