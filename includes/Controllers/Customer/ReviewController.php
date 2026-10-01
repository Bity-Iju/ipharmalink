<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Session;
use App\Upload;

/**
 * Reviews and ratings.
 *
 * Two rules protect the integrity of the ratings:
 *   - a review must sit on a delivered order (you cannot review what you
 *     have not received);
 *   - one review per product per order, enforced by a uniqueness check.
 */
final class ReviewController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /account/reviews
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $customerId = (int) Auth::id();
        $db         = Database::instance();

        $written = $db->all(
            'SELECT r.id, r.rating, r.title, r.body, r.status, r.created_at,
                    r.entity_type, r.product_id, r.pharmacy_id,
                    COALESCE(p.name, ph.name) AS subject,
                    COALESCE(p.slug, ph.slug) AS slug
             FROM reviews r
             LEFT JOIN products p ON p.id = r.product_id
             LEFT JOIN pharmacies ph ON ph.id = r.pharmacy_id
             WHERE r.user_id = ?
             ORDER BY r.id DESC',
            ['user_id' => $customerId]
        );

        $this->view('customer/reviews', [
            'title'   => 'My reviews',
            'heading' => 'My reviews',
            'reviews' => $written,
            // Delivered items with no review yet — populated by Controller::withChrome.
            'pending' => $this->pendingItems($customerId),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /account/reviews/create
    // -----------------------------------------------------------------------
    public function create(Request $request): void
    {
        $customerId = (int) Auth::id();

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'entity_type' => 'required|in:product,pharmacy',
                    'target_id'   => 'required|integer',
                    'rating'      => 'required|integer|between:1,5',
                    'title'       => 'nullable|string|max:150',
                    'body'        => 'required|string|min:10|max:2000',
                    'service_rating'      => 'nullable|integer|between:1,5',
                    'availability_rating' => 'nullable|integer|between:1,5',
                    'delivery_rating'     => 'nullable|integer|between:1,5',
                ],
                $request->all(),
                'customer/review-form',
                '/account/reviews'
            );

            $this->assertReviewable((string) $data['entity_type'], (int) $data['target_id'], $customerId);
            $this->assertNotAlreadyReviewed((string) $data['entity_type'], (int) $data['target_id'], $customerId);

            $db     = Database::instance();
            $isPharmacy = $data['entity_type'] === 'pharmacy';
            $targetId    = (int) $data['target_id'];

            $reviewId = $db->transaction(static function () use ($db, $data, $customerId, $isPharmacy, $targetId, $request): int {
                // Link the review to a delivered order where one exists.
                $orderId = $db->value(
                    'SELECT o.id FROM orders o
                     JOIN order_items oi ON oi.order_id = o.id
                     WHERE o.customer_id = ? AND o.status = "delivered"
                       AND oi.' . ($isPharmacy ? 'pharmacy_id' : 'product_id') . ' = ?
                     ORDER BY o.id DESC LIMIT 1',
                    ['user_id' => $customerId, 'target_id' => $targetId]
                );

                $id = $db->insert('reviews', [
                    'user_id'            => $customerId,
                    'entity_type'        => $data['entity_type'],
                    'product_id'         => $isPharmacy ? null : $targetId,
                    'pharmacy_id'        => $isPharmacy ? $targetId : $db->value(
                        'SELECT pharmacy_id FROM products WHERE id = ?',
                        ['id' => $targetId]
                    ),
                    'order_id'           => $orderId,
                    'rating'             => (int) $data['rating'],
                    'title'              => $data['title'] ?? null,
                    'body'               => $data['body'],
                    'service_rating'     => $data['service_rating'] ?? null,
                    'availability_rating' => $data['availability_rating'] ?? null,
                    'delivery_rating'     => $data['delivery_rating'] ?? null,
                    // Reviews are published unless moderation is switched on.
                    'status'             => \App\Setting::getBool('reviews.require_moderation', false) ? 'pending' : 'published',
                ]);

                if ($request->file('image') !== null) {
                    $saved = Upload::store($request->file('image'), 'review');
                    if ($saved !== null) {
                        $db->insert('review_images', ['review_id' => $id, 'file_path' => $saved['path']]);
                    }
                }

                return $id;
            });

            if ($reviewId > 0) {
                $this->recalculateAverages($data);
            }

            Session::success('Thank you! Your review has been published.');
            Response::redirect('/account/reviews');
        }

        $entityType = (string) $request->query('type', 'product');
        $targetId   = $request->queryInt('id', 0);

        $subject = $entityType === 'pharmacy'
            ? Database::instance()->first('SELECT name, slug FROM pharmacies WHERE id = ?', ['id' => $targetId])
            : Database::instance()->first('SELECT name, slug FROM products WHERE id = ?', ['id' => $targetId]);

        $this->view('customer/review-form', [
            'title'       => 'Write a review',
            'heading'     => 'Write a review',
            'entityType'  => $entityType === 'pharmacy' ? 'pharmacy' : 'product',
            'targetId'    => $targetId,
            'subject'     => $subject,
            'pending'     => $this->pendingItems($customerId),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------

    /** Delivered items with no review yet. @return list<array<string,mixed>> */
    private function pendingItems(int $customerId): array
    {
        return Database::instance()->all(
            'SELECT oi.product_id, oi.product_name, p.slug AS product_slug, o.id AS order_id
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             LEFT JOIN products p ON p.id = oi.product_id
             LEFT JOIN reviews r ON r.user_id = o.customer_id AND r.entity_type = "product"
                                AND r.product_id = oi.product_id AND r.order_id = oi.order_id
             WHERE o.customer_id = ? AND o.status = "delivered" AND r.id IS NULL
             ORDER BY oi.id DESC LIMIT 40',
            ['customer_id' => $customerId]
        );
    }

    /**
     * A customer may only review a pharmacy or product they actually received.
     */
    private function assertReviewable(string $entityType, int $targetId, int $customerId): void
    {
        $column = $entityType === 'pharmacy' ? 'pharmacy_id' : 'product_id';

        $found = Database::instance()->value(
            "SELECT oi.id FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.customer_id = ? AND o.status = 'delivered' AND oi.{$column} = ?
             LIMIT 1",
            ['user_id' => $customerId, 'target_id' => $targetId]
        );

        if ($found === null) {
            throw HttpException::forbidden('You can only review items from an order you have received.');
        }
    }

    private function assertNotAlreadyReviewed(string $entityType, int $targetId, int $customerId): void
    {
        $column = $entityType === 'pharmacy' ? 'pharmacy_id' : 'product_id';

        $exists = Database::instance()->value(
            "SELECT id FROM reviews
             WHERE user_id = ? AND entity_type = ? AND {$column} = ? AND status <> 'rejected'
             LIMIT 1",
            [
                'user_id'      => $customerId,
                'entity_type'  => $entityType,
                'target_id'    => $targetId,
            ]
        );

        if ($exists !== null) {
            Session::error('You have already reviewed that. Thank you.');
            Response::redirect('/account/reviews');
        }
    }

    /**
     * Keep the denormalised rating columns in step with the review table.
     */
    private function recalculateAverages(array $data): void
    {
        $db     = Database::instance();
        $target = (int) $data['target_id'];

        if ($data['entity_type'] === 'product') {
            $stats = $db->first(
                'SELECT ROUND(AVG(rating), 2) AS avg, COUNT(*) AS total
                 FROM reviews WHERE product_id = ? AND status = "published"',
                ['product_id' => $target]
            );
            $db->update('products', [
                'rating_avg'   => (float) ($stats['avg'] ?? 0),
                'rating_count' => (int) ($stats['total'] ?? 0),
            ], 'id = ?', ['id' => $target]);

            return;
        }

        $stats = $db->first(
            'SELECT ROUND(AVG(rating), 2) AS avg, COUNT(*) AS total
             FROM reviews WHERE pharmacy_id = ? AND entity_type = "pharmacy" AND status = "published"',
            ['pharmacy_id' => $target]
        );
        $db->update('pharmacies', [
            'rating_avg'   => (float) ($stats['avg'] ?? 0),
            'rating_count' => (int) ($stats['total'] ?? 0),
        ], 'id = ?', ['id' => $target]);
    }
}
