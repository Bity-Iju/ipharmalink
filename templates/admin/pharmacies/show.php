<?php

/**
 * Admin pharmacy detail & verification — /admin/pharmacies/{id}
 *
 * @var array $pharmacy
 * @var array $documents
 * @var array $stats
 * @var array $wallet
 * @var array $settings
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <img src="<?= e(upload_url($pharmacy['logo'])) ?>" alt="" width="44" height="44"
            class="rounded" style="object-fit:contain">
        <div>
            <h2 class="h5 fw-bold mb-0"><?= e((string) $pharmacy['name']) ?></h2>
            <div class="text-muted small">
                <?= e((string) $pharmacy['city']) ?>, <?= e((string) $pharmacy['state']) ?>
                · <?= status_badge((string) $pharmacy['status']) ?>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>/products" class="btn btn-sm btn-light">Products</a>
        <a href="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>/orders" class="btn btn-sm btn-light">Orders</a>
        <a href="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>/sales" class="btn btn-sm btn-light">Sales</a>
        <a href="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>/logs" class="btn btn-sm btn-light">Activity</a>
        <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light">
            <i class="bi bi-box-arrow-up-right"></i> Storefront
        </a>
    </div>
</div>

<!-- Verification panel -->
<div class="ipl-card p-4 mb-3" style="border-color:var(--ipl-primary)">
    <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-check me-1"></i>Verification decision</h2>

    <?php if (!empty($pharmacy['status_reason'])): ?>
        <div class="alert alert-info small">
            <strong>Reason on file:</strong> <?= e((string) $pharmacy['status_reason']) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>/status">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="status">Decision</label>
                <select name="status" id="status" class="form-select">
                    <?php foreach (['pending', 'approved', 'suspended', 'rejected', 'deactivated'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $pharmacy['status'] === $option ? ' selected' : '' ?>>
                            <?= e(ucfirst($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="commission_rate">Commission rate (%)</label>
                <input type="number" name="commission_rate" id="commission_rate" class="form-control"
                    min="0" max="50" step="0.01"
                    value="<?= e((string) ($pharmacy['commission_rate'] ?? '')) ?>"
                    placeholder="<?= e((string) \App\Setting::getFloat('commission.default_rate_percent', 8)) ?>">
                <div class="form-text">Blank uses the platform default.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="reason">Reason</label>
                <input type="text" name="reason" id="reason" class="form-control"
                    placeholder="Required when rejecting or suspending — the pharmacy sees this">
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Apply decision</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Products',  (int) $stats['products'],   'bi-box-seam',       'primary', '/admin/pharmacies/' . (int) $pharmacy['id'] . '/products'],
        ['Orders',    (int) $stats['orders'],     'bi-receipt',        'info',    '/admin/pharmacies/' . (int) $pharmacy['id'] . '/orders'],
        ['Delivered', (int) $stats['delivered'],  'bi-check2-circle',  'success', '/admin/pharmacies/' . (int) $pharmacy['id'] . '/orders'],
        ['Revenue',   money_compact((float) $stats['revenue']), 'bi-currency-naira', 'warning', '/admin/pharmacies/' . (int) $pharmacy['id'] . '/sales'],
        ['Commission', money_compact((float) $stats['commission']), 'bi-percent', 'danger', '/admin/commissions'],
        ['Customers', (int) $stats['customers'],  'bi-people',         'secondary', '/admin/customers'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-2">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
                'link' => $link,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- Business details -->
        <div class="ipl-card p-4 mb-3">
            <h2 class="h6 fw-bold mb-3">Business details</h2>
            <form method="post" action="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="name">Pharmacy name</label>
                        <input type="text" name="name" id="name" class="form-control" required
                            value="<?= e((string) $pharmacy['name']) ?>">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control"
                            value="<?= e((string) $pharmacy['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            value="<?= e((string) $pharmacy['phone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="registration_number">Registration number</label>
                        <input type="text" id="registration_number" class="form-control" disabled
                            value="<?= e((string) ($pharmacy['registration_number'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="city">City</label>
                        <input type="text" name="city" id="city" class="form-control" required
                            value="<?= e((string) $pharmacy['city']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="state">State</label>
                        <input type="text" name="state" id="state" class="form-control" required
                            value="<?= e((string) $pharmacy['state']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="address">Address</label>
                        <input type="text" name="address" id="address" class="form-control" required
                            value="<?= e((string) $pharmacy['address']) ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                                id="is_featured" <?= (int) $pharmacy['is_featured'] === 1 ? ' checked' : '' ?>>
                            <label class="form-check-label" for="is_featured">Feature on the homepage</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Documents -->
        <div class="ipl-card p-4 mb-3">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-file-earmark-check me-1"></i>Documents</h2>
            <?php if (empty($documents)): ?>
                <p class="small text-muted mb-0">No documents were uploaded.</p>
            <?php else: ?>
                <div class="d-grid gap-2">
                    <?php foreach ($documents as $doc): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded"
                            style="background:#f6f9f8">
                            <div class="min-w-0">
                                <a href="<?= e(upload_url((string) $doc['file_path'])) ?>" target="_blank" rel="noopener"
                                    class="small fw-semibold text-reset d-block text-truncate">
                                    <?= e(ucfirst(str_replace('_', ' ', (string) $doc['doc_type']))) ?>
                                </a>
                                <span class="text-muted" style="font-size:.72rem">
                                    <?= e((string) ($doc['original_name'] ?? 'View file')) ?>
                                </span>
                            </div>
                            <?= status_badge((string) $doc['review_status']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Wallet -->
        <div class="ipl-card p-4 mb-3">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-wallet2 me-1"></i>Wallet</h2>
            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Available</span>
                    <strong class="text-success"><?= money((float) $wallet['balance']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Pending</span>
                    <strong class="text-warning"><?= money((float) $wallet['pending_balance']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Lifetime earned</span>
                    <strong><?= money((float) $wallet['total_earned']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Paid out</span>
                    <strong><?= money((float) $wallet['total_paid']) ?></strong>
                </div>
            </div>
        </div>

        <!-- Owner -->
        <div class="ipl-card p-4">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-person-badge me-1"></i>Pharmacist</h2>
            <div class="small">
                <div class="fw-semibold"><?= e((string) ($pharmacy['pharmacist_name'] ?? $pharmacy['owner_name'] ?? '—')) ?></div>
                <div class="text-muted"><?= e((string) ($pharmacy['owner_email'] ?? '')) ?></div>
                <div class="text-muted"><?= e((string) ($pharmacy['owner_phone'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</div>