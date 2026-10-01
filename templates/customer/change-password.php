<?php

/**
 * Change password — /account/change-password
 *
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-3">Change your password</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/account/change-password" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label required" for="current_password">Current password</label>
                    <div class="input-group">
                        <input type="password" name="current_password" id="current_password"
                            class="form-control" required autocomplete="current-password">
                        <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <?= $err('current_password') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="password">New password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required
                            autocomplete="new-password">
                        <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">At least 8 characters, with an uppercase letter and a number.</div>
                    <?= $err('password') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="password_confirmation">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="form-control" required autocomplete="new-password" data-password="#password">
                </div>

                <button type="submit" class="btn btn-primary">Update password</button>
            </form>
        </div>
    </div>
</div>