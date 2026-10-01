<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Setting;

/**
 * CMS pages: legal, policy, about and the FAQ. Bodies are admin-authored
 * HTML, so they are rendered unescaped but stripped of script/style tags at
 * save time by the admin controller.
 */
final class PageController extends Controller
{
    public function about(Request $request): void
    {
        $this->renderPage('about', 'About us', 'Learn how ' . $this->platformName() . ' connects you to verified pharmacies for genuine medicines and fast delivery.');
    }

    public function terms(Request $request): void
    {
        $this->renderPage('terms', 'Terms of service', 'The terms governing your use of ' . $this->platformName() . ' and purchases made through our pharmacy marketplace.');
    }

    public function privacy(Request $request): void
    {
        $this->renderPage('privacy', 'Privacy policy', 'How ' . $this->platformName() . ' collects, uses and protects your personal and health information.');
    }

    public function deliveryPolicy(Request $request): void
    {
        $this->renderPage('delivery-policy', 'Delivery policy', 'Delivery times, fees, coverage areas and what happens when an order cannot be delivered.');
    }

    public function refundPolicy(Request $request): void
    {
        $this->renderPage('refund-policy', 'Refund &amp; returns policy', 'How refunds, returns and order cancellations work on ' . $this->platformName() . '.');
    }

    public function pharmacyTerms(Request $request): void
    {
        $this->renderPage('pharmacy-terms', 'Pharmacy terms', 'Terms that pharmacies must accept to sell and dispense medicines through ' . $this->platformName() . '.');
    }

    // -----------------------------------------------------------------------
    //  /page/{slug}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $page = Database::instance()->first(
            'SELECT * FROM pages WHERE slug = ? AND is_published = 1 LIMIT 1',
            ['slug' => $this->param('slug', $params)]
        );
        if ($page === null) {
            throw HttpException::notFound('That page does not exist.');
        }

        $this->view('storefront/page', [
            'title'           => (string) $page['meta_title'] ?: (string) $page['title'],
            'metaDescription' => (string) $page['meta_description'],
            'page'            => $page,
            'heading'         => (string) $page['title'],
        ]);
    }

    // -----------------------------------------------------------------------
    //  /faq
    // -----------------------------------------------------------------------
    public function faq(Request $request): void
    {
        $db      = Database::instance();
        $groups  = $db->all('SELECT category, COUNT(*) AS total FROM faqs WHERE is_active = 1 GROUP BY category ORDER BY category ASC');
        $current = (string) $request->query('group', '');

        $faqs = $current !== ''
            ? $db->all('SELECT * FROM faqs WHERE is_active = 1 AND category = ? ORDER BY sort_order ASC, id ASC', ['category' => $current])
            : $db->all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY category ASC, sort_order ASC, id ASC');

        $this->view('storefront/faq', [
            'title'           => 'Frequently asked questions',
            'metaDescription' => 'Answers about ordering medicines, delivery, prescriptions, payments and refunds on ' . $this->platformName() . '.',
            'faqs'            => $faqs,
            'groups'          => $groups,
            'currentGroup'    => $current,
        ]);
    }

    // -----------------------------------------------------------------------

    private function renderPage(string $slug, string $title, string $description): void
    {
        $page = Database::instance()->first(
            'SELECT * FROM pages WHERE slug = ? AND is_published = 1 LIMIT 1',
            ['slug' => $slug]
        );

        $this->view('storefront/page', [
            'title'           => $page !== null ? ((string) $page['meta_title'] ?: $title) : $title,
            'metaDescription' => $page !== null && $page['meta_description'] !== null
                ? (string) $page['meta_description']
                : $description,
            'page'            => $page,
            'heading'         => $title,
        ]);
    }

    private function platformName(): string
    {
        return Setting::getString('general.platform_name', 'iPharmaLink');
    }
}
