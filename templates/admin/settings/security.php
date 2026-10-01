<?php

/**
 * Admin security settings — /admin/security-settings
 *
 * @var array $values
 */
$v = static fn(string $key, string $default = ''): string => e((string) ($values[$key] ?? $default));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Security settings</h2>
</div>

<form method="post" action="/admin/security-settings" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Login protection</h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="max_login_attempts">Failed attempts before lockout</label>
                    <input type="number" min="3" max="20" name="max_login_attempts" id="max_login_attempts"
                        class="form-control" value="<?= $v('max_login_attempts', '5') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lockout_minutes">Lockout (minutes)</label>
                    <input type="number" min="1" max="1440" name="lockout_minutes" id="lockout_minutes"
                        class="form-control" value="<?= $v('lockout_minutes', '15') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="password_min_length">Minimum password length</label>
                    <input type="number" min="6" max="64" name="password_min_length" id="password_min_length"
                        class="form-control" value="<?= $v('password_min_length', '8') ?>">
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Sessions &amp; transport</h3>

            <div class="mb-3">
                <label class="form-label" for="session_lifetime">Session lifetime (seconds)</label>
                <input type="number" min="300" max="86400" name="session_lifetime" id="session_lifetime"
                    class="form-control" value="<?= $v('session_lifetime', '3600') ?>">
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="force_https"
                    id="force_https" value="1" <?= $v('force_https') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="force_https">Force HTTPS</label>
            </div>

            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="require_prescription_upload"
                    id="require_prescription_upload" value="1"
                    <?= $v('require_prescription_upload') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="require_prescription_upload">
                    Require a prescription image for prescription-only medicines
                </label>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="alert alert-warning small">
            <i class="bi bi-shield-lock me-1"></i>
            These controls apply platform-wide. Forcing HTTPS without a valid certificate
            will lock administrators out.
        </div>

        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save settings
        </button>
    </div>
</form>