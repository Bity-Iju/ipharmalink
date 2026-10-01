<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\Request;

/**
 * Static marketing page: /how-it-works
 *
 * Explains the two-tier model (wholesale supplier to retail pharmacy to end
 * customer) which is the single most misunderstood thing about the platform.
 */
final class HowItWorksController extends Controller
{
    public function index(Request $request): void
    {
        $db = Database::instance();

        // Live proof points, so the page never drifts from the live data.
        $stats = [
            'pharmacies' => (int) $db->value(
                "SELECT COUNT(*) FROM pharmacies WHERE status = 'approved' AND deleted_at IS NULL"
            ),
            'products'  => (int) $db->value('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND is_active = 1'),
            'categories' => (int) $db->value('SELECT COUNT(*) FROM categories WHERE is_active = 1 AND deleted_at IS NULL'),
        ];

        $this->view('storefront/how-it-works', [
            'title'           => 'How iPharmaLink works',
            'metaDescription' => 'See how wholesale suppliers sell in bulk to retail pharmacies on iPharmaLink, and how those pharmacies then serve their own customers.',
            'heading'         => 'How iPharmaLink works',
            'stats'           => $stats,
        ]);
    }
}
