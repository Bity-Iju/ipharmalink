<?php
/** @var array $errors */
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="auth-wrap">
    <div class="auth-card is-wide">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem"><i class="bi bi-boxes"></i></span>
                <h1 class="h5 fw-bold mb-1">Register as a wholesale supplier</h1>
                <p class="text-muted small mb-0">Create a verified business account to supply retail pharmacies.</p>
            </div>

            <div class="alert alert-info small">Your account remains locked until an administrator reviews your business and licence documents.</div>
            <?php if (!empty($errors)): ?><div class="alert alert-danger small">Please correct the errors below.</div><?php endif; ?>

            <form method="post" action="/supplier/register" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Account and responsible pharmacist</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ([
                        'full_name' => ['Account holder name', 'text'],
                        'pharmacist_name' => ['Pharmacist / owner name', 'text'],
                        'contact_person' => ['Contact person', 'text'],
                        'email' => ['Business email', 'email'],
                        'phone' => ['Phone number', 'tel'],
                    ] as $name => [$label, $type]): ?>
                        <div class="col-md-6">
                            <label class="form-label required" for="<?= e($name) ?>"><?= e($label) ?></label>
                            <input class="form-control" type="<?= e($type) ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" required value="<?= old($name) ?>">
                            <?= $fieldError($name) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-6">
                        <label class="form-label required" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="new-password">
                        <?= $fieldError('password') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password_confirmation">Confirm password</label>
                        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Business details</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ([
                        'name' => ['Trading name', 'text'],
                        'registered_name' => ['Registered business name', 'text'],
                        'registration_number' => ['Business / licence registration number', 'text'],
                        'state' => ['State', 'text'],
                        'city' => ['City', 'text'],
                        'business_address' => ['Business address', 'text'],
                        'warehouse_address' => ['Warehouse address', 'text'],
                    ] as $name => [$label, $type]): ?>
                        <div class="col-md-6">
                            <label class="form-label<?= in_array($name, ['warehouse_address'], true) ? '' : ' required' ?>" for="<?= e($name) ?>"><?= e($label) ?></label>
                            <input class="form-control" type="<?= e($type) ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $name !== 'warehouse_address' ? 'required' : '' ?> value="<?= old($name) ?>">
                            <?= $fieldError($name) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_areas">Delivery areas</label>
                        <input class="form-control" type="text" id="delivery_areas" name="delivery_areas" value="<?= old('delivery_areas') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="opening_hours">Opening hours</label>
                        <input class="form-control" type="text" id="opening_hours" name="opening_hours" value="<?= old('opening_hours') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="logo">Business logo</label>
                        <input class="form-control" type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">Business description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?= old('description') ?></textarea>
                        <?= $fieldError('description') ?>
                    </div>
                </div>

                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Verification documents</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="license_document">Supplier licence / registration certificate</label>
                        <input class="form-control" type="file" id="license_document" name="license_document" required accept=".pdf,image/jpeg,image/png">
                        <?= $fieldError('license_document') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="incorporation_document">Certificate of incorporation</label>
                        <input class="form-control" type="file" id="incorporation_document" name="incorporation_document" accept=".pdf,image/jpeg,image/png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tax_document">Tax certificate</label>
                        <input class="form-control" type="file" id="tax_document" name="tax_document" accept=".pdf,image/jpeg,image/png">
                    </div>
                </div>

                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">Settlement account</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ([
                        'bank_name' => 'Bank name',
                        'bank_account_name' => 'Account name',
                        'bank_account_number' => 'Account number',
                    ] as $name => $label): ?>
                        <div class="col-md-4">
                            <label class="form-label required" for="<?= e($name) ?>"><?= e($label) ?></label>
                            <input class="form-control" type="text" id="<?= e($name) ?>" name="<?= e($name) ?>" required value="<?= old($name) ?>">
                            <?= $fieldError($name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required>
                    <label class="form-check-label small" for="terms">I confirm these business details are accurate and accept the <a href="/terms" target="_blank" rel="noopener">Terms of Service</a>.</label>
                    <?= $fieldError('terms') ?>
                </div>
                <button type="submit" class="btn btn-primary w-100">Submit for verification</button>
            </form>

            <div class="text-center mt-3 small">Already registered? <a href="/supplier/login">Supplier sign in</a></div>
        </div>
    </div>
</div>