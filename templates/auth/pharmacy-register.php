<?php

/**
 * Pharmacy (vendor) registration — /pharmacy/register
 *
 * A long form, split into sections. Accounts are created in `pending` state
 * and cannot sign in until an administrator approves them.
 *
 * @var array  $errors
 * @var array  $states
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="auth-wrap">
    <div class="auth-card is-wide">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-shop-window"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Sell on <?= e(\App\Config::str('app.name')) ?></h1>
                <p class="text-muted small mb-0">
                    Reach thousands of customers. Every pharmacy is verified before it can trade.
                </p>
            </div>

            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>
                Your storefront goes live once our compliance team has reviewed your licence documents —
                usually within two business days.
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/pharmacy/register" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <!-- ===== Pharmacist / owner ===== -->
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-person-badge me-1"></i> Pharmacist in charge
                </h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="pharmacist_name">Your full name</label>
                        <input type="text" name="pharmacist_name" id="pharmacist_name" class="form-control" required
                            value="<?= old('pharmacist_name') ?>">
                        <?= $err('pharmacist_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            placeholder="0803 000 0000" value="<?= old('phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="email">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                            value="<?= old('email') ?>">
                        <?= $err('email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?= $err('password') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password_confirmation">Confirm password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            class="form-control" required data-password="#password">
                    </div>
                </div>

                <!-- ===== Pharmacy details ===== -->
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-shop me-1"></i> Pharmacy details
                </h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="name">Pharmacy (trading) name</label>
                        <input type="text" name="name" id="name" class="form-control" required
                            value="<?= old('name') ?>" placeholder="HealthPlus Pharmacy">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="legal_name">Registered business name</label>
                        <input type="text" name="legal_name" id="legal_name" class="form-control" required
                            value="<?= old('legal_name') ?>">
                        <?= $err('legal_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="registration_number">Pharmacy registration number</label>
                        <input type="text" name="registration_number" id="registration_number" class="form-control" required
                            value="<?= old('registration_number') ?>" placeholder="PCN/LA/2024/0001">
                        <div class="form-text">As issued by your state pharmacy council.</div>
                        <?= $err('registration_number') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="regulatory_body">Regulatory body</label>
                        <input type="text" name="regulatory_body" id="regulatory_body" class="form-control" required
                            value="<?= old('regulatory_body', 'Pharmacy Council of Nigeria (PCN)') ?>">
                        <?= $err('regulatory_body') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">About your pharmacy</label>
                        <textarea name="description" id="description" rows="3" class="form-control" required
                            placeholder="Tell customers about your services, specialisms and experience."><?= old('description') ?></textarea>
                        <div class="form-text">At least 30 characters.</div>
                        <?= $err('description') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="state">State</label>
                        <select name="state" id="state" class="form-select" required>
                            <option value="">Select a state</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?= e($state) ?>" <?= old('state') === $state ? ' selected' : '' ?>>
                                    <?= e($state) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('state') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="city">City / town</label>
                        <input type="text" name="city" id="city" class="form-control" required
                            value="<?= old('city') ?>">
                        <?= $err('city') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="address">Street address</label>
                        <input type="text" name="address" id="address" class="form-control" required
                            value="<?= old('address') ?>">
                        <?= $err('address') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="latitude">Latitude</label>
                        <input type="text" name="latitude" id="latitude" class="form-control" required
                            value="<?= old('latitude') ?>" placeholder="6.5244">
                        <div class="form-text">Used to calculate delivery distance. Open the map and drop a pin.</div>
                        <?= $err('latitude') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="longitude">Longitude</label>
                        <input type="text" name="longitude" id="longitude" class="form-control" required
                            value="<?= old('longitude') ?>" placeholder="3.3792">
                        <?= $err('longitude') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="open_time">Opens at</label>
                        <input type="time" name="open_time" id="open_time" class="form-control" required
                            value="<?= old('open_time', '08:00') ?>">
                        <?= $err('open_time') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="close_time">Closes at</label>
                        <input type="time" name="close_time" id="close_time" class="form-control" required
                            value="<?= old('close_time', '20:00') ?>">
                        <?= $err('close_time') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="website">Website</label>
                        <input type="url" name="website" id="website" class="form-control"
                            placeholder="https://" value="<?= old('website') ?>">
                        <?= $err('website') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_radius_km">Delivery radius (km)</label>
                        <input type="number" name="delivery_radius_km" id="delivery_radius_km"
                            class="form-control" min="0" max="200" value="<?= old('delivery_radius_km', '10') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estimated_delivery_minutes">Typical delivery time (minutes)</label>
                        <input type="number" name="estimated_delivery_minutes" id="estimated_delivery_minutes"
                            class="form-control" min="10" max="1440" value="<?= old('estimated_delivery_minutes', '60') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="logo">Pharmacy logo</label>
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        <div class="form-text">Square image works best. Max 5 MB.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cover_image">Cover image</label>
                        <input type="file" name="cover_image" id="cover_image" class="form-control" accept="image/*">
                    </div>
                </div>

                <!-- ===== Verification documents ===== -->
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-file-earmark-check me-1"></i> Verification documents
                </h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="doc_licence">Pharmacy licence / registration certificate</label>
                        <input type="file" name="licence_document" id="doc_licence" class="form-control" required
                            accept=".pdf,image/jpeg,image/png">
                        <?= $err('documents') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="doc_incorporation">Certificate of incorporation</label>
                        <input type="file" name="incorporation_document" id="doc_incorporation" class="form-control"
                            accept=".pdf,image/jpeg,image/png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="doc_tax">Tax clearance certificate</label>
                        <input type="file" name="tax_document" id="doc_tax" class="form-control"
                            accept=".pdf,image/jpeg,image/png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="doc_id">Pharmacist's ID</label>
                        <input type="file" name="identification_document" id="doc_id" class="form-control"
                            accept=".pdf,image/jpeg,image/png">
                    </div>
                </div>

                <!-- ===== Banking ===== -->
                <h2 class="h6 fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-bank me-1"></i> Payout details
                </h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label required" for="bank_name">Bank name</label>
                        <input type="text" name="bank_name" id="bank_name" class="form-control" required
                            value="<?= old('bank_name') ?>">
                        <?= $err('bank_name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="bank_account_name">Account name</label>
                        <input type="text" name="bank_account_name" id="bank_account_name" class="form-control" required
                            value="<?= old('bank_account_name') ?>">
                        <?= $err('bank_account_name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="bank_account_number">Account number</label>
                        <input type="text" name="bank_account_number" id="bank_account_number"
                            class="form-control" required value="<?= old('bank_account_number') ?>" inputmode="numeric">
                        <?= $err('bank_account_number') ?>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required>
                    <label class="form-check-label small" for="terms">
                        I confirm the information given is accurate, I am licensed to operate this pharmacy,
                        and I accept the <a href="/pharmacy-terms" target="_blank">Pharmacy Terms</a> and
                        <a href="/terms" target="_blank">Terms of Service</a>.
                    </label>
                    <?= $err('terms') ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Submit for verification</button>
            </form>

            <div class="text-center mt-3 small">
                Already registered? <a href="/pharmacy/login">Sign in</a>
            </div>
        </div>
    </div>
</div>