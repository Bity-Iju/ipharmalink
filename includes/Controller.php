<?php

/**
 * iPharmaLink :: Base controller
 * ---------------------------------------------------------------------------
 * Controllers stay thin: they validate, call a service, and choose a
 * response. Shared plumbing (view rendering, redirect-or-render, pagination)
 * lives here so no controller repeats it.
 */

declare(strict_types=1);

namespace App;

abstract class Controller
{
    protected Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
    }

    /**
     * Render a template inside a layout.
     *
     * Auth screens (templates/auth/*) are full-bleed and carry no storefront
     * chrome, so they get the bare layout automatically — regardless of the
     * default passed in by the caller.
     *
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $layout ??= 'layouts/main';

        if (str_starts_with($template, 'auth.')) {
            $layout = 'layouts/auth';
        }

        View::render($template, $this->withChrome($data), $layout);
    }

    /**
     * Attach the shared chrome data plus the standard page variables.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function withChrome(array $data): array
    {
        static $chrome = null;

        if ($chrome === null) {
            $db        = Database::instance();
            $chrome    = [
                'headerCategories' => $db->all(
                    'SELECT id, name, slug FROM categories
                     WHERE parent_id IS NULL AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY sort_order ASC, name ASC LIMIT 12'
                ),
                'footerPages' => $db->all(
                    'SELECT slug, title FROM pages
                     WHERE is_published = 1 AND slug NOT IN
                     ("about","terms","privacy","delivery-policy","refund-policy","pharmacy-terms")
                     ORDER BY title ASC LIMIT 5'
                ),
            ];
            View::share('headerCategories', $chrome['headerCategories']);
            View::share('footerPages', $chrome['footerPages']);
        }

        $user     = Auth::user();
        $customer = $user !== null ? (int) $user['id'] : null;

        $data += [
            'title'            => Config::str('app.name', 'iPharmaLink'),
            'metaDescription'  => Setting::getString('general.meta_description', ''),
            'errors'           => [],
            'old'              => [],
            'user'             => $user,
        ];

        // Reviewable orders power both the review form and reorder links.
        if ($customer !== null && \App\Auth::isCustomer()) {
            $data['reviewableOrderItems'] ??= $this->reviewableItems($customer);
        }

        return $data;
    }

    /**
     * Delivered order lines the customer has not yet reviewed.
     *
     * @return list<array<string,mixed>>
     */
    private function reviewableItems(int $customerId): array
    {
        return Database::instance()->all(
            'SELECT oi.id AS order_item_id, oi.product_id, oi.product_name, oi.pharmacy_id,
                    p.slug AS product_slug, ph.name AS pharmacy_name, ph.slug AS pharmacy_slug
             FROM order_items oi
             JOIN orders o      ON o.id = oi.order_id
             JOIN pharmacies ph ON ph.id = oi.pharmacy_id
             LEFT JOIN products p ON p.id = oi.product_id
             LEFT JOIN reviews r ON r.user_id = o.customer_id
                                AND r.entity_type = "product"
                                AND r.product_id = oi.product_id
                                AND r.order_id = oi.order_id
             WHERE o.customer_id = ? AND o.status = "delivered" AND r.id IS NULL
             ORDER BY oi.id DESC LIMIT 60',
            ['customer_id' => $customerId]
        );
    }

    /** Render without a layout (partials, error pages, PDF/CSV views). */
    protected function partial(string $template, array $data = []): void
    {
        View::render($template, $data, null);
    }

    /**
    * Validate input, re-render the form on failure, and return cleaned data
    * to the controller on success so the action can finish its own workflow.
     *
     * @param  array<string,string|list<string>> $rules
     * @param  array<string,mixed>              $data
     * @return array<string,mixed>
     */
    protected function validate(array $rules, array $data, string $errorView, string $redirectTo, string $successMessage = ''): array
    {
        try {
            $clean = (new Validator($data))->validate($rules);
        } catch (ValidationException $e) {
            Session::flashInput($data);
            $errors = $e->errors();
            $first  = reset($errors);
            $this->view($errorView, [
                'title'      => 'Please correct the errors below',
                'errors'     => $errors,
                'errorCount' => count($errors),
            ] + $data);
            exit;
        }

        if ($successMessage !== '') {
            Session::success($successMessage);
        }
        return $clean;
    }

    /** @param array<string,mixed> $params */
    protected function param(string $name, array $params, string $default = ''): string
    {
        $value = $params[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }

    protected function page(): int
    {
        return max(1, $this->request->queryInt('page', 1));
    }

    protected function perPage(int $default = 24): int
    {
        $configured = (int) Setting::get('system.pagination_per_page', $default);
        return $default > $configured ? $default : $configured;
    }
}
