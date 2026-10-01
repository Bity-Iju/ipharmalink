<?php

/**
 * Admin delivery settings — /admin/delivery-settings
 *
 * @var array $values
 */
$v = static fn(string $key, string $default = ''): string => e((string) ($values[$key] ?? $default));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Delivery settings</h2>
</div>

<form method="post" action="/admin/delivery-settings" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Fees</h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="flat_fee">Flat fee</label>
                    <input type="number" step="0.01" min="0" name="flat_fee" id="flat_fee"
                        class="form-control" value="<?= $v('flat_fee', '0') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="per_km_fee">Per km fee</label>
                    <input type="number" step="0.01" min="0" name="per_km_fee" id="per_km_fee"
                        class="form-control" value="<?= $v('per_km_fee', '0') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="free_over">Free delivery over</label>
                    <input type="number" step="0.01" min="0" name="free_over" id="free_over"
                        class="form-control" value="<?= $v('free_over', '0') ?>">
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Service levels</h3>

            <div class="mb-3">
                <label class="form-label" for="default_eta_days">Default delivery time (days)</label>
                <input type="number" min="0" name="default_eta_days" id="default_eta_days"
                    class="form-control" value="<?= $v('default_eta_days', '1') ?>">
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="pickup_enabled"
                    id="pickup_enabled" value="1" <?= $v('pickup_enabled') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="pickup_enabled">Allow pickup orders</label>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="otp_required"
                    id="otp_required" value="1" <?= $v('otp_required') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="otp_required">Require an OTP to confirm delivery</label>
            </div>

            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="proof_required"
                    id="proof_required" value="1" <?= $v('proof_required') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="proof_required">Require proof of delivery photo</label>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            A pharmacy may override these on its own settings page, but it can never charge
            below the platform minimum.
        </div>

        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save settings
        </button>
    </div>
</form>