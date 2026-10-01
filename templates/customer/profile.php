<?php

/**
 * Customer profile — /account/profile
 *
 * @var array $user
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-3">Profile details</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/account/profile" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="full_name">Full name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required
                            value="<?= e((string) ($user['full_name'] ?? old('full_name'))) ?>">
                        <?= $err('full_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            value="<?= e((string) ($user['phone'] ?? old('phone'))) ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="email">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                            value="<?= e((string) ($user['email'] ?? old('email'))) ?>">
                        <div class="form-text">Changing this will require you to verify the new address again.</div>
                        <?= $err('email') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="profile_image">Profile picture</label>
                        <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
                        <div class="form-text">Square image, max 3 MB.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="ipl-card p-4 mt-3">
            <h2 class="h6 fw-bold mb-2">Account status</h2>
            <div class="small text-muted">
                <div class="d-flex justify-content-between py-1">
                    <span>Account</span><span><?= status_badge((string) $user['status']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>Email verified</span>
                    <span><?= $user['email_verified_at'] !== null ? 'Yes' : 'No' ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>Member since</span>
                    <span><?= e(date('j F Y', strtotime((string) $user['created_at']))) ?></span>
                </div>
            </div>
            <a href="/account/change-password" class="btn btn-light btn-sm mt-3">
                <i class="bi bi-key me-1"></i> Change password
            </a>
        </div>
    </div>
</div>