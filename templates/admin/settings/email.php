<?php

/**
 * Admin email settings — /admin/email-settings
 *
 * @var array  $values
 * @var bool   $hasSmtpPassword
 * @var string $mask
 */
$v = static fn(string $key, string $default = ''): string => e((string) ($values[$key] ?? $default));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Email settings</h2>
</div>

<form method="post" action="/admin/email-settings" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Sender</h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required" for="driver">Driver</label>
                    <select name="driver" id="driver" class="form-select">
                        <?php foreach (['log' => 'Log to file (development)', 'smtp' => 'SMTP', 'mail' => 'PHP mail()'] as $option => $label): ?>
                            <option value="<?= e($option) ?>" <?= $v('driver', 'log') === $option ? ' selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="from_name">From name</label>
                    <input type="text" name="from_name" id="from_name" class="form-control"
                        value="<?= $v('from_name', 'iPharmaLink') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="from_email">From email</label>
                    <input type="email" name="from_email" id="from_email" class="form-control"
                        value="<?= $v('from_email') ?>">
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">SMTP</h3>
            <p class="text-muted small">
                The password is write-only. Leave it blank to keep the stored value.
            </p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="smtp_host">Host</label>
                    <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?= $v('smtp_host') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="smtp_port">Port</label>
                    <input type="number" min="1" max="65535" name="smtp_port" id="smtp_port"
                        class="form-control" value="<?= $v('smtp_port', '587') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="smtp_encryption">Encryption</label>
                    <select name="smtp_encryption" id="smtp_encryption" class="form-select">
                        <?php foreach (['tls', 'ssl', ''] as $option): ?>
                            <option value="<?= e($option) ?>" <?= $v('smtp_encryption', 'tls') === $option ? ' selected' : '' ?>>
                                <?= $option === '' ? 'None' : e(strtoupper($option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_username">Username</label>
                    <input type="text" name="smtp_username" id="smtp_username" class="form-control" value="<?= $v('smtp_username') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_password">Password</label>
                    <input type="password" name="smtp_password" id="smtp_password" class="form-control"
                        autocomplete="new-password"
                        placeholder="<?= $hasSmtpPassword ? e($mask) . ' (stored)' : 'Not set' ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save settings
        </button>
    </div>
</form>