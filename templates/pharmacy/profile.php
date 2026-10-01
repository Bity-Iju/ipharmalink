<?php

/**
 * Pharmacy storefront profile — /pharmacy/profile
 *
 * @var array $pharmacy
 * @var array $documents
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$val = static fn(string $field, mixed $default = ''): string =>
(string) (\App\Session::old($field, $pharmacy[$field] ?? $default));

// The owner may change these; an admin handles licence/commission changes.
$locked = ['status' => $pharmacy['status'], 'commission_rate' => $pharmacy['commission_rate']];
?>
<form method="post" action="/pharmacy/profile" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Storefront details</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="name">Pharmacy name</label>
                        <input type="text" name="name" id="name" class="form-control" required value="<?= e($val('name')) ?>">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="legal_name">Registered business name</label>
                        <input type="text" name="legal_name" id="legal_name" class="form-control" value="<?= e($val('legal_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="pharmacist_name">Pharmacist in charge</label>
                        <input type="text" name="pharmacist_name" id="pharmacist_name" class="form-control" required
                            value="<?= e($val('pharmacist_name')) ?>">
                        <?= $err('pharmacist_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="website">Website</label>
                        <input type="url" name="website" id="website" class="form-control"
                            placeholder="https://" value="<?= e($val('website')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">About your pharmacy</label>
                        <textarea name="description" id="description" rows="4" class="form-control" required><?= e($val('description')) ?></textarea>
                        <?= $err('description') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required value="<?= e($val('phone')) ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required value="<?= e($val('email')) ?>">
                        <?= $err('email') ?>
                    </div>
                </div>
            </div>

            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Location &amp; hours</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="address">Street address</label>
                        <input type="text" name="address" id="address" class="form-control" required value="<?= e($val('address')) ?>">
                        <?= $err('address') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="city">City</label>
                        <input type="text" name="city" id="city" class="form-control" required value="<?= e($val('city')) ?>">
                        <?= $err('city') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="state">State</label>
                        <input type="text" name="state" id="state" class="form-control" required value="<?= e($val('state')) ?>">
                        <?= $err('state') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="latitude">Latitude</label>
                        <input type="text" name="latitude" id="latitude" class="form-control" required value="<?= e($val('latitude')) ?>">
                        <?= $err('latitude') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="longitude">Longitude</label>
                        <input type="text" name="longitude" id="longitude" class="form-control" required value="<?= e($val('longitude')) ?>">
                        <?= $err('longitude') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="open_time">Opens at</label>
                        <input type="time" name="open_time" id="open_time" class="form-control" required
                            value="<?= e(substr($val('open_time', '08:00:00'), 0, 5)) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="close_time">Closes at</label>
                        <input type="time" name="close_time" id="close_time" class="form-control" required
                            value="<?= e(substr($val('close_time', '20:00:00'), 0, 5)) ?>">
                    </div>
                </div>
            </div>

            <div class="ipl-card p-4">
                <h2 class="h6 fw-bold mb-3">Branding</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="logo">Logo</label>
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        <?php if (!empty($pharmacy['logo'])): ?>
                            <img src="<?= e(upload_url((string) $pharmacy['logo'])) ?>" alt="" class="mt-2"
                                style="width:56px;height:56px;object-fit:contain" data-preview="#logoPreview">
                        <?php else: ?>
                            <img src="/assets/images/placeholder.svg" alt="" class="mt-2" id="logoPreview"
                                style="width:56px;height:56px;object-fit:contain">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="cover_image">Cover image</label>
                        <input type="file" name="cover_image" id="cover_image" class="form-control" accept="image/*">
                        <div class="form-text">Displayed as the banner on your storefront.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ipl-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-2">Account status</h2>
                <?= status_badge((string) $locked['status']) ?>
                <p class="small text-muted mt-2 mb-0">
                    Only an administrator can change your status or commission rate.
                </p>
                <?php if (!empty($pharmacy['status_reason'])): ?>
                    <div class="alert alert-info small mt-2 mb-0"><?= e((string) $pharmacy['status_reason']) ?></div>
                <?php endif; ?>
            </div>

            <div class="ipl-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Payout details</h2>
                <div class="mb-2">
                    <label class="form-label" for="bank_name">Bank name</label>
                    <input type="text" name="bank_name" id="bank_name" class="form-control" value="<?= e($val('bank_name')) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="bank_account_name">Account name</label>
                    <input type="text" name="bank_account_name" id="bank_account_name" class="form-control"
                        value="<?= e($val('bank_account_name')) ?>">
                </div>
                <div>
                    <label class="form-label" for="bank_account_number">Account number</label>
                    <input type="text" name="bank_account_number" id="bank_account_number" class="form-control"
                        inputmode="numeric" value="<?= e($val('bank_account_number')) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Save profile</button>
        </div>
    </div>
</form>

<?php if (!empty($documents)): ?>
    <div class="ipl-card p-4 mt-3">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-file-earmark-check me-1"></i>Verification documents</h2>
        <div class="table-responsive">
            <table class="table ipl-table mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>File</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="small"><?= e(ucfirst(str_replace('_', ' ', (string) $doc['doc_type']))) ?></td>
                            <td class="small">
                                <a href="<?= e(upload_url((string) $doc['file_path'])) ?>" target="_blank" rel="noopener">
                                    <?= e((string) ($doc['original_name'] ?? 'View')) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $doc['uploaded_at']))) ?></td>
                            <td><?= status_badge((string) $doc['review_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>