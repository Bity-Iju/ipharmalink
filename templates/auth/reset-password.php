<?php

/**
 * Choose a new password — /reset-password?token=…
 *
 * @var string $token
 * @var array  $errors
 */
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-shield-lock"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Choose a new password</h1>
                <p class="text-muted small mb-0">Pick something you have not used before</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">Please correct the errors below.</div>
            <?php endif; ?>

            <form method="post" action="/reset-password" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label class="form-label required" for="password">New password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required
                            autocomplete="new-password" autofocus>
                        <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">At least 8 characters, with an uppercase letter and a number.</div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback d-block"><?= e(implode(' ', $errors['password'])) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="password_confirmation">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="form-control" required autocomplete="new-password" data-password="#password">
                </div>

                <button type="submit" class="btn btn-primary w-100">Update password</button>
            </form>
        </div>
    </div>
</div>