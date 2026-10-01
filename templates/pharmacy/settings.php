<?php

/**
 * Pharmacy settings — /pharmacy/settings
 *
 * @var array $pharmacy
 * @var array $settings
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$val = static function (string $field, mixed $default = '') use ($pharmacy): string {
    $old = \App\Session::old($field, null);
    return (string) ($old ?? $pharmacy[$field] ?? $default);
};
$on = static function (string $key, bool $default = false) use ($settings): bool {
    $old = \App\Session::old($key, null);
    if ($old !== null) {
        return in_array(strtolower((string) $old), ['1', 'on', 'yes', 'true'], true);
    }
    return (($settings[$key] ?? (string) (int) $default) === '1');
};
?>
<form method="post" action="/pharmacy/settings" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-truck me-1"></i>Delivery</h2>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="delivery_available" value="1"
                        id="delivery_available" <?= (int) $pharmacy['delivery_available'] === 1 ? ' checked' : '' ?>>
                    <label class="form-check-label" for="delivery_available">
                        <strong>Offer home delivery</strong>
                        <span class="d-block small text-muted">Uncheck if you are pickup-only.</span>
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_fee">Delivery fee (₦)</label>
                        <input type="number" name="delivery_fee" id="delivery_fee" class="form-control"
                            min="0" step="0.01" value="<?= e($val('delivery_fee', '0')) ?>">
                        <?= $err('delivery_fee') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="free_delivery_threshold">Free delivery over (₦)</label>
                        <input type="number" name="free_delivery_threshold" id="free_delivery_threshold"
                            class="form-control" min="0" step="0.01"
                            value="<?= e($val('free_delivery_threshold', '0')) ?>">
                        <div class="form-text">0 disables free delivery.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_radius_km">Delivery radius (km)</label>
                        <input type="number" name="delivery_radius_km" id="delivery_radius_km"
                            class="form-control" min="0" max="200" step="0.1"
                            value="<?= e($val('delivery_radius_km', '10')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estimated_delivery_minutes">Typical delivery (minutes)</label>
                        <input type="number" name="estimated_delivery_minutes" id="estimated_delivery_minutes"
                            class="form-control" min="10" max="1440"
                            value="<?= e($val('estimated_delivery_minutes', '60')) ?>">
                    </div>
                </div>
            </div>

            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-bag-check me-1"></i>Orders</h2>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="pickup_available" value="1"
                        id="pickup_available" <?= (int) $pharmacy['pickup_available'] === 1 ? ' checked' : '' ?>>
                    <label class="form-check-label" for="pickup_available">Allow counter pickup</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="accept_orders_automatically" value="1"
                        id="accept_orders_automatically" <?= (int) $pharmacy['accept_orders_automatically'] === 1 ? ' checked' : '' ?>>
                    <label class="form-check-label" for="accept_orders_automatically">
                        <strong>Auto-accept paid orders</strong>
                        <span class="d-block small text-muted">Off means you confirm each order manually.</span>
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="min_order_value">Minimum order value (₦)</label>
                        <input type="number" name="min_order_value" id="min_order_value" class="form-control"
                            min="0" step="0.01" value="<?= e($val('min_order_value', '0')) ?>">
                        <?= $err('min_order_value') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="preparation_minutes">Preparation time (minutes)</label>
                        <input type="number" name="preparation_minutes" id="preparation_minutes"
                            class="form-control" min="5" max="1440"
                            value="<?= e($val('preparation_minutes', '30')) ?>">
                    </div>
                </div>
            </div>

            <div class="ipl-card p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-bell me-1"></i>Notifications</h2>
                <?php
                $toggles = [
                    'notify_new_order' => 'Notify me about new orders',
                    'notify_low_stock' => 'Alert me when stock is low',
                    'notify_expiry'    => 'Alert me before stock expires',
                    'email_new_order'  => 'Email me about new orders',
                    'sms_new_order'    => 'Text me about new orders',
                ];
                foreach ($toggles as $key => $label): ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="1"
                            id="<?= e($key) ?>" <?= $on($key) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-credit-card me-1"></i>Accepted payment methods</h2>
                <p class="small text-muted">
                    Customers pay through the platform. These appear on your storefront so customers
                    know what to expect.
                </p>
                <?php
                $methods = [
                    'card'         => 'Card (via Paystack / Flutterwave)',
                    'transfer'     => 'Bank transfer',
                    'cash'         => 'Cash on delivery',
                    'usdd'         => 'USSD',
                ];
                $selected = explode(',', (string) ($settings['payment_methods'] ?? 'card,transfer'));
                foreach ($methods as $value => $label): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_methods[]"
                            value="<?= e($value) ?>" id="pm_<?= e($value) ?>"
                            <?= in_array($value, $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pm_<?= e($value) ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ipl-card p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-1"></i>At a glance</h2>
                <div class="small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Status</span><span><?= status_badge((string) $pharmacy['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Commission rate</span>
                        <span><?= e($pharmacy['commission_rate'] !== null
                                    ? (float) $pharmacy['commission_rate'] . '%'
                                    : \App\Setting::getFloat('commission.default_rate_percent', 8) . '% (default)') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Storefront</span>
                        <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" target="_blank" rel="noopener">View</a>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Save settings</button>
        </div>
    </div>
</form>