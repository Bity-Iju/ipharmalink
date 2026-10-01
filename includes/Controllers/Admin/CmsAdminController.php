<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Session;
use App\Upload;

/**
 * Admin content management: review moderation, banners, static pages, FAQs,
 * the contact inbox and the admin notification feed.
 */
final class CmsAdminController extends Controller
{
    private const PER_PAGE = 20;

    // -----------------------------------------------------------------------
    //  Reviews
    // -----------------------------------------------------------------------

    /** GET /admin/reviews */
    public function reviews(Request $request): void
    {
        $status  = $request->trimmed('status');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = $status !== '' ? 'WHERE r.status = :status' : '';
        $params = $status !== '' ? ['status' => $status] : [];

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) FROM reviews r $where", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $where, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT r.*, u.full_name AS author,
                            COALESCE(p.name, ph.name) AS subject
                     FROM reviews r
                     JOIN users u ON u.id = r.user_id
                     LEFT JOIN products p   ON p.id = r.product_id
                     LEFT JOIN pharmacies ph ON ph.id = r.pharmacy_id
                     $where
                     ORDER BY r.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $this->view('admin/cms/reviews', [
            'title'     => 'Reviews',
            'paginator' => $paginator,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    /** POST /admin/reviews/{id} */
    public function moderateReview(Request $request, array $params): void
    {
        $id     = (int) $this->param('id', $params);
        $status = $request->trimmed('status');

        if (!in_array($status, ['published', 'rejected', 'pending'], true)) {
            throw new HttpException(422, 'Choose a valid moderation decision.');
        }

        $review = Database::instance()->first('SELECT * FROM reviews WHERE id = ?', ['id' => $id]);
        if ($review === null) {
            throw new HttpException(404, 'Review not found.');
        }

        Database::instance()->update(
            'reviews',
            ['status' => $status, 'moderated_by' => (int) Auth::id()],
            'id = :id',
            ['id' => $id]
        );

        // Re-aggregate the target's rating so the storefront stays in step.
        $this->recalculateRating((string) $review['entity_type'], (int) $review['product_id'], (int) $review['pharmacy_id']);

        (new AuditService($request))->logChange(
            'admin.review.moderated',
            'review',
            $id,
            ['status' => $review['status']],
            ['status' => $status]
        );

        Session::success('Review ' . $status . '.');
        Response::back('/admin/reviews');
    }

    // -----------------------------------------------------------------------
    //  Banners
    // -----------------------------------------------------------------------

    /** GET /admin/banners */
    public function banners(Request $request): void
    {
        $this->view('admin/cms/banners', [
            'title'   => 'Banners',
            'banners' => Database::instance()->all('SELECT * FROM banners ORDER BY sort_order ASC, id DESC'),
            'errors'  => [],
        ], 'layouts/dashboard');
    }

    /** POST /admin/banners */
    public function storeBanner(Request $request): void
    {
        $data = $this->bannerInput($request);
        $id   = Database::instance()->insert('banners', $data);

        (new AuditService($request))->log('admin.banner.created', 'banner', $id, $data);

        Session::success('Banner created.');
        Response::back('/admin/banners');
    }

    /** POST /admin/banners/{id} */
    public function updateBanner(Request $request, array $params): void
    {
        $id     = (int) $this->param('id', $params);
        $banner = Database::instance()->first('SELECT * FROM banners WHERE id = ?', ['id' => $id]);
        if ($banner === null) {
            throw new HttpException(404, 'Banner not found.');
        }

        $data   = $this->bannerInput($request);
        $before = $banner;
        Database::instance()->update('banners', $data, 'id = :id', ['id' => $id]);

        (new AuditService($request))->logChange('admin.banner.updated', 'banner', $id, $before, $data);

        Session::success('Banner updated.');
        Response::back('/admin/banners');
    }

    /** POST /admin/banners/{id}/delete */
    public function destroyBanner(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $banner = Database::instance()->first('SELECT image FROM banners WHERE id = ?', ['id' => $id]);

        if ($banner !== null) {
            Upload::delete($banner['image']);
            Database::instance()->delete('banners', 'id = :id', ['id' => $id]);
            (new AuditService($request))->log('admin.banner.deleted', 'banner', $id, 'Banner deleted');
        }

        Session::success('Banner deleted.');
        Response::back('/admin/banners');
    }

    // -----------------------------------------------------------------------
    //  Pages
    // -----------------------------------------------------------------------

    /** GET /admin/pages */
    public function pages(Request $request): void
    {
        $this->view('admin/cms/pages', [
            'title' => 'Pages',
            'pages' => Database::instance()->all(
                'SELECT id, slug, title, meta_title, is_published, updated_at
                 FROM pages ORDER BY title ASC'
            ),
        ], 'layouts/dashboard');
    }

    /** GET /admin/pages/create */
    public function createPage(Request $request): void
    {
        $this->view('admin/cms/page-form', [
            'title'  => 'New page',
            'page'   => null,
            'errors' => [],
        ], 'layouts/dashboard');
    }

    /** GET /admin/pages/{id} */
    public function editPage(Request $request, array $params): void
    {
        $page = $this->findPage((int) $this->param('id', $params));

        $this->view('admin/cms/page-form', [
            'title'  => 'Edit ' . $page['title'],
            'page'   => $page,
            'errors' => [],
        ], 'layouts/dashboard');
    }

    /** POST /admin/pages/{id} */
    public function updatePage(Request $request, array $params): void
    {
        $id   = (int) $this->param('id', $params);
        $page = $this->findPage($id);
        $data = $this->pageInput($request);

        Database::instance()->update('pages', $data + ['updated_by' => (int) Auth::id()], 'id = :id', ['id' => $id]);

        (new AuditService($request))->logChange('admin.page.updated', 'page', $id, $page, $data);

        Session::success('Page updated.');
        Response::back('/admin/pages');
    }

    /**
     * POST /admin/pages/create
     *
     * Registered separately because the create route has no {id} segment.
     */
    public function storePage(Request $request): void
    {
        $data = $this->pageInput($request);
        $id   = Database::instance()->insert('pages', $data + ['updated_by' => (int) Auth::id()]);

        (new AuditService($request))->log('admin.page.created', 'page', $id, $data);

        Session::success('Page created.');
        Response::back('/admin/pages');
    }

    /** POST /admin/pages/{id}/delete */
    public function destroyPage(Request $request, array $params): void
    {
        $id   = (int) $this->param('id', $params);
        $page = $this->findPage($id);

        // The storefront links to these slugs; refuse to orphan the chrome.
        $protected = ['about', 'terms', 'privacy', 'delivery-policy', 'refund-policy', 'pharmacy-terms'];
        if (in_array($page['slug'], $protected, true)) {
            throw new HttpException(422, 'This page is linked from the site footer and cannot be deleted.');
        }

        Database::instance()->delete('pages', 'id = :id', ['id' => $id]);
        (new AuditService($request))->log('admin.page.deleted', 'page', $id, 'Page deleted');

        Session::success('Page deleted.');
        Response::back('/admin/pages');
    }

    // -----------------------------------------------------------------------
    //  FAQs
    // -----------------------------------------------------------------------

    /** GET /admin/faqs */
    public function faqs(Request $request): void
    {
        $this->view('admin/cms/faqs', [
            'title'  => 'FAQs',
            'faqs'   => Database::instance()->all('SELECT * FROM faqs ORDER BY category ASC, sort_order ASC, id ASC'),
            'errors' => [],
        ], 'layouts/dashboard');
    }

    /** POST /admin/faqs */
    public function storeFaq(Request $request): void
    {
        $data = $this->faqInput($request);
        $id   = Database::instance()->insert('faqs', $data);

        (new AuditService($request))->log('admin.faq.created', 'faq', $id, $data);

        Session::success('FAQ added.');
        Response::back('/admin/faqs');
    }

    /** POST /admin/faqs/{id} */
    public function updateFaq(Request $request, array $params): void
    {
        $id  = (int) $this->param('id', $params);
        $faq = Database::instance()->first('SELECT * FROM faqs WHERE id = ?', ['id' => $id]);
        if ($faq === null) {
            throw new HttpException(404, 'FAQ not found.');
        }

        $data = $this->faqInput($request);
        Database::instance()->update('faqs', $data, 'id = :id', ['id' => $id]);

        (new AuditService($request))->logChange('admin.faq.updated', 'faq', $id, $faq, $data);

        Session::success('FAQ updated.');
        Response::back('/admin/faqs');
    }

    /** POST /admin/faqs/{id}/delete */
    public function destroyFaq(Request $request, array $params): void
    {
        $id = (int) $this->param('id', $params);
        Database::instance()->delete('faqs', 'id = :id', ['id' => $id]);

        (new AuditService($request))->log('admin.faq.deleted', 'faq', $id, 'FAQ deleted');

        Session::success('FAQ deleted.');
        Response::back('/admin/faqs');
    }

    // -----------------------------------------------------------------------
    //  Contact inbox
    // -----------------------------------------------------------------------

    /** GET /admin/contact-messages */
    public function contactMessages(Request $request): void
    {
        $status  = $request->trimmed('status');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = $status !== '' ? 'WHERE status = :status' : '';
        $params = $status !== '' ? ['status' => $status] : [];

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) FROM contact_messages $where", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $where, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all("SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);
            },
            $perPage,
            $page
        );

        $this->view('admin/cms/contact-messages', [
            'title'     => 'Contact messages',
            'paginator' => $paginator,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    /** POST /admin/contact-messages/{id} */
    public function updateMessage(Request $request, array $params): void
    {
        $id     = (int) $this->param('id', $params);
        $status = $request->trimmed('status');

        if (!in_array($status, ['new', 'read', 'replied', 'archived'], true)) {
            throw new HttpException(422, 'Choose a valid message status.');
        }

        $message = Database::instance()->first('SELECT * FROM contact_messages WHERE id = ?', ['id' => $id]);
        if ($message === null) {
            throw new HttpException(404, 'Message not found.');
        }

        Database::instance()->update('contact_messages', ['status' => $status], 'id = :id', ['id' => $id]);

        (new AuditService($request))->logChange(
            'admin.contact_message.status_changed',
            'contact_message',
            $id,
            ['status' => $message['status']],
            ['status' => $status]
        );

        Session::success('Message marked as ' . $status . '.');
        Response::back('/admin/contact-messages');
    }

    // -----------------------------------------------------------------------
    //  Notifications
    // -----------------------------------------------------------------------

    /** GET /admin/notifications */
    public function notifications(Request $request): void
    {
        $adminId = (int) Auth::id();

        $this->view('admin/cms/notifications', [
            'title'         => 'Notifications',
            'notifications' => Database::instance()->all(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 60',
                ['user_id' => $adminId]
            ),
            'unread'        => NotificationService::unreadCount($adminId),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    /**
     * Recompute an entity's cached rating from its published reviews.
     */
    private function recalculateRating(string $entityType, int $productId, int $pharmacyId): void
    {
        $db = Database::instance();

        if ($entityType === 'product' && $productId > 0) {
            $stats = $db->first(
                "SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS total
                 FROM reviews WHERE entity_type = 'product' AND product_id = ? AND status = 'published'",
                ['product_id' => $productId]
            );
            $db->update(
                'products',
                ['rating_avg' => round((float) $stats['avg_rating'], 2), 'rating_count' => (int) $stats['total']],
                'id = :id',
                ['id' => $productId]
            );
            return;
        }

        if ($entityType === 'pharmacy' && $pharmacyId > 0) {
            $stats = $db->first(
                "SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS total
                 FROM reviews WHERE entity_type = 'pharmacy' AND pharmacy_id = ? AND status = 'published'",
                ['pharmacy_id' => $pharmacyId]
            );
            $db->update(
                'pharmacies',
                ['rating_avg' => round((float) $stats['avg_rating'], 2), 'rating_count' => (int) $stats['total']],
                'id = :id',
                ['id' => $pharmacyId]
            );
        }
    }

    /** @return array<string,mixed> */
    private function bannerInput(Request $request): array
    {
        $image = null;
        $file  = $request->file('image');
        if ($file !== null) {
            $stored = Upload::store($file, 'banners');
            if ($stored !== null) {
                $image = $stored['path'];
            }
        }

        return [
            'title'       => $request->trimmed('title'),
            'subtitle'    => $request->trimmed('subtitle'),
            'image'       => $image,
            'button_text' => $request->trimmed('button_text'),
            'button_link' => $request->trimmed('button_link'),
            'placement'   => $request->trimmed('placement', 'home_hero'),
            'sort_order'  => $request->int('sort_order'),
            'starts_at'   => $request->trimmed('starts_at') !== '' ? $request->trimmed('starts_at') : null,
            'ends_at'     => $request->trimmed('ends_at') !== '' ? $request->trimmed('ends_at') : null,
            'is_active'   => $request->bool('is_active') ? 1 : 0,
        ];
    }

    /** @return array<string,mixed> */
    private function pageInput(Request $request): array
    {
        $slug = slugify_text($request->trimmed('slug') !== '' ? $request->trimmed('slug') : $request->trimmed('title'));

        return [
            'slug'             => $slug,
            'title'            => $request->trimmed('title'),
            'body'             => $request->input('body', ''),
            'meta_title'       => $request->trimmed('meta_title'),
            'meta_description' => $request->trimmed('meta_description'),
            'is_published'     => $request->bool('is_published') ? 1 : 0,
        ];
    }

    /** @return array<string,mixed> */
    private function faqInput(Request $request): array
    {
        return [
            'category'   => $request->trimmed('category', 'General'),
            'question'   => $request->trimmed('question'),
            'answer'     => $request->input('answer', ''),
            'sort_order' => $request->int('sort_order'),
            'is_active'  => $request->bool('is_active') ? 1 : 0,
        ];
    }

    /**
     * Load a CMS page row, or fail closed with a 404.
     *
     * Named findPage() because Controller already defines page() as the
     * current pagination number.
     *
     * @return array<string,mixed>
     */
    private function findPage(int $id): array
    {
        $page = Database::instance()->first('SELECT * FROM pages WHERE id = ?', ['id' => $id]);

        if ($page === null) {
            throw new HttpException(404, 'Page not found.');
        }

        return $page;
    }
}
