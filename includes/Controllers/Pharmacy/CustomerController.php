<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\NotificationService;
use App\View;

/**
 * Pharmacy-side views of its customers, reviews and notifications, plus the
 * delivery assignment screen.
 */
final class CustomerController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /pharmacy/customers
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(DISTINCT o.customer_id) FROM pharmacy_orders po
                 JOIN orders o ON o.id = po.order_id WHERE po.pharmacy_id = ?',
                ['pharmacy_id' => $pharmacyId]
            ),
            static function (Database $db, int $perPage, int $offset) use ($pharmacyId): array {
                return $db->all(
                    'SELECT u.id, u.full_name, u.email, u.phone, u.created_at,
                            COUNT(DISTINCT po.order_id) AS orders,
                            COALESCE(SUM(CASE WHEN po.status NOT IN ("cancelled","refunded") THEN po.total ELSE 0 END), 0) AS spent,
                            MAX(po.created_at) AS last_order
                     FROM pharmacy_orders po
                     JOIN orders o ON o.id = po.order_id
                     JOIN users u ON u.id = o.customer_id
                     WHERE po.pharmacy_id = ?
                     GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at
                     ORDER BY spent DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                    ['pharmacy_id' => $pharmacyId]
                );
            },
            $this->perPage(20),
            $this->page()
        );

        $this->view('pharmacy/customers', [
            'title'     => 'Customers',
            'heading'   => 'My customers',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/reviews
    // -----------------------------------------------------------------------
    public function reviews(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $summary = $db->first(
            "SELECT COUNT(*) AS total, ROUND(AVG(rating), 1) AS average,
                    ROUND(AVG(service_rating), 1) AS service,
                    ROUND(AVG(availability_rating), 1) AS availability,
                    ROUND(AVG(delivery_rating), 1) AS delivery
             FROM reviews WHERE pharmacy_id = ? AND status = 'published'",
            ['pharmacy_id' => $pharmacyId]
        ) ?? ['total' => 0, 'average' => 0, 'service' => 0, 'availability' => 0, 'delivery' => 0];

        $reviews = $db->all(
            'SELECT r.*, u.full_name AS author
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.pharmacy_id = ? AND r.status = "published"
             ORDER BY r.id DESC LIMIT 100',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/reviews', [
            'title'   => 'Reviews',
            'heading' => 'Customer reviews',
            'sidebar' => View::capture('pharmacy/partials/sidebar'),
            'summary' => $summary,
            'reviews' => $reviews,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/notifications
    // -----------------------------------------------------------------------
    public function notifications(Request $request): void
    {
        $userId = (int) Auth::id();

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ?',
                ['user_id' => $userId]
            ),
            static function (Database $db, int $perPage, int $offset) use ($userId): array {
                return $db->all(
                    'SELECT * FROM notifications WHERE user_id = ?
                     ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                    ['user_id' => $userId]
                );
            },
            $this->perPage(15),
            $this->page()
        );

        $this->view('pharmacy/notifications', [
            'title'     => 'Notifications',
            'heading'   => 'Notifications',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
            'unread'    => NotificationService::unreadCount($userId),
        ], 'layouts/dashboard');
    }
}
