<?php

/**
 * Forgot password — /forgot-password
 *
 * @var array $errors
 */
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-key"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Reset your password</h1>
                <p class="text-muted small mb-0">We will email you a secure reset link</p>
            </div>

            <p class="small text-muted">
                Enter the email address on your account and we will send you a link to choose a new password.
                The link expires in one hour.
            </p>

            <form method="post" action="/forgot-password" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label required" for="email">Email address</label>
                    <input type="email" name="email" id="email" class="form-control" required
                        autocomplete="email" autofocus value="<?= old('email') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback d-block"><?= e(implode(' ', $errors['email'])) ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary w-100">Send reset link</button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/login"><i class="bi bi-arrow-left me-1"></i> Back to sign in</a>
            </div>
        </div>
    </div>
</div>