<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Services\CatalogService;
use App\Setting;

/**
 * Public pharmacy marketplace: the directory of approved pharmacies and each
 * pharmacy's own storefront at /pharmacy/{slug}.
 */
final class PharmacyController extends Controller
{
    private CatalogService $catalog;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->catalog = new CatalogService();
    }

    // -----------------------------------------------------------------------
    //  /pharmacies
    // -----------------------------------------------------------------------
    public function directory(Request $request): void
    {
        $db = Database::instance();

        [$where, $params] = $this->directoryFilters($request);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM pharmacies ph
                 LEFT JOIN users u ON u.id = ph.owner_id
                 WHERE {$where}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($where, $params): array {
                return $db->all(
                    "SELECT ph.id, ph.name, ph.slug, ph.logo, ph.city, ph.state, ph.description,
                            ph.open_time, ph.close_time, ph.delivery_available, ph.pickup_available,
                            ph.delivery_fee, ph.estimated_delivery_minutes, ph.rating_avg, ph.rating_count,
                            ph.is_featured, ph.cover_image, ph.phone, ph.website,
                            u.full_name AS owner_name,
                            (SELECT COUNT(*) FROM products p
                              WHERE p.pharmacy_id = ph.id AND p.is_active = 1 AND p.is_visible = 1
                                AND p.deleted_at IS NULL) AS product_count
                     FROM pharmacies ph
                     LEFT JOIN users u ON u.id = ph.owner_id
                     WHERE {$where}
                     ORDER BY ph.is_featured DESC, ph.rating_avg DESC, ph.rating_count DESC, ph.name ASC
                     LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(12),
            $this->page()
        );

        $this->view('storefront/pharmacies', [
            'title'           => 'Pharmacy directory — browse verified pharmacies',
            'metaDescription' => 'Find licensed pharmacies near you on ' . Setting::getString('general.platform_name', 'iPharmaLink')
                . '. Compare delivery fees, opening hours and customer ratings.',
            'paginator'       => $paginator,
            'filters'         => [
                'q'      => (string) $request->query('q', ''),
                'state'  => (string) $request->query('state', ''),
                'city'   => (string) $request->query('city', ''),
                'service' => (string) $request->query('service', ''),
                'sort'   => (string) $request->query('sort', 'featured'),
            ],
            'states'          => $db->all("SELECT DISTINCT state FROM pharmacies WHERE status = 'approved' AND state <> '' ORDER BY state ASC"),
        ]);
    }

    // -----------------------------------------------------------------------
    //  /pharmacy/{slug}
    // -----------------------------------------------------------------------
    public function store(Request $request, array $params): void
    {
        $slug = $this->param('slug', $params);
        $db   = Database::instance();

        $pharmacy = $db->first(
            "SELECT ph.*, u.full_name AS owner_name, u.email AS owner_email,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.pharmacy_id = ph.id AND p.is_active = 1 AND p.is_visible = 1
                        AND p.deleted_at IS NULL) AS product_count
             FROM pharmacies ph
             LEFT JOIN users u ON u.id = ph.owner_id
             WHERE ph.slug = ? AND ph.status = 'approved' AND ph.deleted_at IS NULL
             LIMIT 1",
            ['slug' => $slug]
        );

        if ($pharmacy === null) {
            throw HttpException::notFound('We could not find that pharmacy storefront.');
        }

        $filters = [
            'q'        => trim((string) $request->query('q', '')),
            'category' => $request->queryInt('category', 0),
            'pharmacy' => (int) $pharmacy['id'],
            'sort'     => (string) $request->query('sort', 'relevance'),
            'in_stock' => (string) $request->query('in_stock', ''),
        ];

        $paginator = $this->catalog->search($filters, $this->page(), $this->perPage(24));

        // Review themes: service, availability and delivery experience.
        $reviewSummary = $db->first(
            "SELECT COUNT(*) AS total,
                    ROUND(AVG(rating), 1) AS average,
                    ROUND(AVG(service_rating), 1) AS service,
                    ROUND(AVG(availability_rating), 1) AS availability,
                    ROUND(AVG(delivery_rating), 1) AS delivery
             FROM reviews
             WHERE pharmacy_id = ? AND entity_type = 'pharmacy' AND status = 'published'",
            ['pharmacy_id' => $pharmacy['id']]
        ) ?? ['total' => 0, 'average' => 0, 'service' => 0, 'availability' => 0, 'delivery' => 0];

        $reviews = $db->all(
            'SELECT r.id, r.rating, r.service_rating, r.availability_rating, r.delivery_rating,
                    r.title, r.body, r.created_at, u.full_name AS author
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.pharmacy_id = ? AND r.entity_type = "pharmacy" AND r.status = "published"
             ORDER BY r.id DESC LIMIT 8',
            ['pharmacy_id' => $pharmacy['id']]
        );

        // JSON-LD: a pharmacy is a MedicalBusiness / LocalBusiness.
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type'    => 'Pharmacy',
            'name'     => (string) $pharmacy['name'],
            'image'    => upload_url($pharmacy['logo']),
            'url'      => url('/pharmacy/' . (string) $pharmacy['slug']),
            'telephone' => (string) $pharmacy['phone'],
            'email'    => (string) $pharmacy['email'],
            'description' => str_excerpt((string) $pharmacy['description'], 250),
            'address'  => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => (string) $pharmacy['address'],
                'addressLocality' => (string) $pharmacy['city'],
                'addressRegion'   => (string) $pharmacy['state'],
                'addressCountry'  => 'NG',
            ],
            'geo' => ($pharmacy['latitude'] !== null && $pharmacy['longitude'] !== null) ? [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $pharmacy['latitude'],
                'longitude' => (float) $pharmacy['longitude'],
            ] : null,
            'openingHoursSpecification' => [[
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens'     => substr((string) $pharmacy['open_time'], 0, 5),
                'closes'    => substr((string) $pharmacy['close_time'], 0, 5),
            ]],
            'aggregateRating' => (int) $pharmacy['rating_count'] > 0 ? [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format((float) $pharmacy['rating_avg'], 1, '.', ''),
                'reviewCount' => (int) $pharmacy['rating_count'],
            ] : null,
        ];

        $scripts = '<script type="application/ld+json">'
            . json_encode(array_filter($structuredData, static fn($v) => $v !== null), JSON_UNESCAPED_SLASHES)
            . '</script>';

        $this->view('storefront/pharmacy', [
            'title'           => (string) $pharmacy['name'] . ' – medicines online',
            'metaDescription' => sprintf(
                'Order from %s in %s. %s products available, %s delivery, %s. Rated %s out of 5 by customers.',
                (string) $pharmacy['name'],
                (string) $pharmacy['city'],
                (int) $pharmacy['product_count'],
                (int) $pharmacy['delivery_available'] === 1 ? 'Home delivery available' : 'Pickup only',
                (int) $pharmacy['delivery_available'] === 1 ? 'delivery in about ' . (int) $pharmacy['estimated_delivery_minutes'] . ' minutes' : 'no delivery',
                number_format((float) $pharmacy['rating_avg'], 1)
            ),
            'og'              => [
                'title' => (string) $pharmacy['name'],
                'image' => $pharmacy['logo'],
                'url'   => url('/pharmacy/' . (string) $pharmacy['slug']),
            ],
            'pharmacy'        => $pharmacy,
            'paginator'       => $paginator,
            'filters'         => $filters,
            'categories'      => $db->all(
                'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
                 FROM categories c
                 LEFT JOIN products p ON p.category_id = c.id AND p.pharmacy_id = ?
                                    AND p.is_active = 1 AND p.deleted_at IS NULL
                 WHERE c.is_active = 1 AND c.deleted_at IS NULL
                 GROUP BY c.id, c.name, c.slug
                 HAVING product_count > 0
                 ORDER BY product_count DESC LIMIT 20',
                ['pharmacy_id' => $pharmacy['id']]
            ),
            'reviewSummary'   => $reviewSummary,
            'reviews'         => $reviews,
            'scripts'         => $scripts,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Filters
    // -----------------------------------------------------------------------

    /** @return array{0:string,1:array<string,mixed>} */
    private function directoryFilters(Request $request): array
    {
        $where  = ["ph.status = 'approved'", 'ph.deleted_at IS NULL'];
        $params = [];

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like       = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $where[]    = '(ph.name LIKE :q1 OR ph.city LIKE :q2 OR ph.state LIKE :q3 OR ph.description LIKE :q4)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $state = (string) $request->query('state', '');
        if ($state !== '') {
            $where[]          = 'ph.state = :state';
            $params['state']  = $state;
        }

        $city = (string) $request->query('city', '');
        if ($city !== '') {
            $where[]        = 'ph.city = :city';
            $params['city'] = $city;
        }

        $service = (string) $request->query('service', '');
        if ($service === 'delivery') {
            $where[] = 'ph.delivery_available = 1';
        } elseif ($service === 'pickup') {
            $where[] = 'ph.pickup_available = 1';
        }

        return [implode(' AND ', $where), $params];
    }
}
