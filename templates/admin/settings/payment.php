<?php

/**
 * Admin payment settings — /admin/payment-settings
 *
 * @var array  $values
 * @var array  $gateways
 * @var string $mask
 */
$v = static fn(string $key, string $default = ''): string => e((string) ($values[$key] ?? $default));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Payment settings</h2>
</div>

<form method="post" action="/admin/payment-settings" class="row g-4 mb-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Accepted methods</h3>

            <div class="mb-3">
                <label class="form-label" for="default_gateway">Default gateway</label>
                <select name="default_gateway" id="default_gateway" class="form-select">
                    <?php foreach (['manual', 'bank_transfer', 'wallet', 'paystack', 'flutterwave'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $v('default_gateway', 'manual') === $option ? ' selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $option))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="allow_bank_transfer"
                    id="allow_bank_transfer" value="1" <?= $v('allow_bank_transfer') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="allow_bank_transfer">Allow manual bank transfer</label>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="allow_wallet"
                    id="allow_wallet" value="1" <?= $v('allow_wallet') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="allow_wallet">Allow wallet payment</label>
            </div>

            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="require_proof"
                    id="require_proof" value="1" <?= $v('require_proof') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="require_proof">Require payment proof upload</label>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Bank transfer details</h3>
            <p class="text-muted small">
                Shown to buyers when they choose manual transfer. Payment is only marked paid after
                an admin or the supplier verifies the transfer.
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="bank_name">Bank name</label>
                    <input type="text" name="bank_name" id="bank_name" class="form-control" value="<?= $v('bank_name') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="account_number">Account number</label>
                    <input type="text" name="account_number" id="account_number" class="form-control" value="<?= $v('account_number') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="account_name">Account name</label>
                    <input type="text" name="account_name" id="account_name" class="form-control" value="<?= $v('account_name') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Limits</h3>
            <label class="form-label" for="minimum_order">Minimum order value</label>
            <input type="number" step="0.01" min="0" name="minimum_order" id="minimum_order"
                class="form-control" value="<?= $v('minimum_order', '0') ?>">
        </div>

        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save settings
        </button>
    </div>
</form>

<h3 class="h6 fw-bold mb-2">Payment gateways</h3>

<?php if ($gateways === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-credit-card"></i></div>
        <p class="mb-0">No gateways are configured yet.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($gateways as $gateway): ?>
            <div class="col-lg-6">
                <form method="post" action="/admin/gateways/<?= e((string) $gateway['code']) ?>" class="ipl-card p-3 h-100" novalidate>
                    <?= csrf_field() ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="h6 fw-bold mb-0"><?= e((string) $gateway['name']) ?></h4>
                        <?= status_badge((int) $gateway['is_enabled'] === 1 ? 'active' : 'disabled') ?>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_enabled"
                            id="en-<?= e((string) $gateway['code']) ?>" value="1"
                            <?= (int) $gateway['is_enabled'] === 1 ? ' checked' : '' ?>>
                        <label class="form-check-label" for="en-<?= e((string) $gateway['code']) ?>">Enabled</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_sandbox"
                            id="sb-<?= e((string) $gateway['code']) ?>" value="1"
                            <?= (int) $gateway['is_sandbox'] === 1 ? ' checked' : '' ?>>
                        <label class="form-check-label" for="sb-<?= e((string) $gateway['code']) ?>">Sandbox mode</label>
                    </div>

                    <?php if ($gateway['credentials'] !== []): ?>
                        <div class="small text-muted mb-0">
                            <i class="bi bi-shield-lock me-1"></i>
                            Secrets stored: <?= e(implode(', ', $gateway['credentials'])) ?>.
                            Leave a field blank to keep the stored value.
                        </div>
                    <?php endif; ?>

                    <button class="btn btn-sm btn-primary mt-3 w-100">Save gateway</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>