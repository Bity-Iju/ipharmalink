<?php

/**
 * Admin general settings — /admin/settings
 *
 * @var array $values
 */
$v = static fn(string $key, string $default = ''): string => e((string) ($values[$key] ?? $default));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">General settings</h2>
</div>

<form method="post" action="/admin/settings" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Identity</h3>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="site_name">Site name</label>
                    <input type="text" name="site_name" id="site_name" class="form-control" required
                        value="<?= $v('site_name', 'iPharmaLink') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tagline">Tagline</label>
                    <input type="text" name="tagline" id="tagline" class="form-control"
                        value="<?= $v('tagline') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="meta_description">Meta description</label>
                    <textarea name="meta_description" id="meta_description" class="form-control" rows="2"
                        maxlength="320"><?= $v('meta_description') ?></textarea>
                </div>
            </div>
        </div>

        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Contact</h3>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="support_email">Support email</label>
                    <input type="email" name="support_email" id="support_email" class="form-control"
                        value="<?= $v('support_email') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="support_phone">Support phone</label>
                    <input type="text" name="support_phone" id="support_phone" class="form-control"
                        value="<?= $v('support_phone') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Registered address</label>
                    <input type="text" name="address" id="address" class="form-control" value="<?= $v('address') ?>">
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Commerce</h3>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="currency">Currency code</label>
                    <input type="text" name="currency" id="currency" class="form-control"
                        value="<?= $v('currency', 'NGN') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="currency_symbol">Currency symbol</label>
                    <input type="text" name="currency_symbol" id="currency_symbol" class="form-control"
                        value="<?= $v('currency_symbol', '₦') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="default_tax_rate">Default tax rate %</label>
                    <input type="number" step="0.01" min="0" name="default_tax_rate" id="default_tax_rate"
                        class="form-control" value="<?= $v('default_tax_rate', '0') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="pagination_per_page">Products per page</label>
                    <input type="number" min="6" max="100" name="pagination_per_page" id="pagination_per_page"
                        class="form-control" value="<?= $v('pagination_per_page', '24') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Behaviour</h3>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="enable_reviews"
                    id="enable_reviews" value="1" <?= ($values['enable_reviews'] ?? '') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="enable_reviews">Enable customer reviews</label>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="maintenance_mode"
                    id="maintenance_mode" value="1" <?= ($values['maintenance_mode'] ?? '') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="maintenance_mode">Maintenance mode</label>
            </div>

            <?php if (($values['maintenance_mode'] ?? '') === '1'): ?>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Only administrators can browse the site while this is on.
                </div>
            <?php endif; ?>
        </div>

        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save settings
        </button>
    </div>
</form>