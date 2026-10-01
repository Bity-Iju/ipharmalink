<?php

/**
 * Customer registration — /register
 *
 * @var array $errors
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
                    <i class="bi bi-capsule-pill"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Create your account</h1>
                <p class="text-muted small mb-0">
                    Order medicines from verified pharmacies with delivery or pickup
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">
                    <strong>Please correct the errors below.</strong>
                </div>
            <?php endif; ?>

            <form method="post" action="/register" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="full_name">Full name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required
                            autocomplete="name" value="<?= old('full_name') ?>">
                        <?= $err('full_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            autocomplete="tel" placeholder="0803 000 0000" value="<?= old('phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="email">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                            autocomplete="email" value="<?= old('email') ?>">
                        <?= $err('email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password">Password</label>
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
                    <div class="col-md-6">
                        <label class="form-label required" for="password_confirmation">Confirm password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            class="form-control" required autocomplete="new-password"
                            data-password="#password">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required
                                <?= old('terms') !== '' ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="terms">
                                I agree to the <a href="/terms" target="_blank">Terms of Service</a>
                                and <a href="/privacy" target="_blank">Privacy Policy</a>
                            </label>
                            <?= $err('terms') ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">Create account</button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-3 small">
                Already have an account? <a href="/login">Sign in</a>
            </div>
        </div>
    </div>
</div>