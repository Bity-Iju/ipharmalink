<?php

/**
 * Customer sign-in — /login
 *
 * @var array $errors
 */
$identifier = old('identifier');
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-capsule-pill"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Welcome back</h1>
                <p class="text-muted small mb-0">Sign in to your <?= e(\App\Config::str('app.name')) ?> account</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">
                    <?php foreach ($errors as $messages): ?>
                        <?php foreach ($messages as $message): ?>
                            <div><?= e($message) ?></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="identifier">Email or phone</label>
                    <input type="text" name="identifier" id="identifier" class="form-control" required
                        autocomplete="username" autofocus
                        value="<?= e($identifier) ?>" placeholder="you@example.com">
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label" for="password">Password</label>
                        <a href="/forgot-password" class="small">Forgot password?</a>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required
                            autocomplete="current-password">
                        <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </form>

            <div class="d-flex flex-wrap gap-2 justify-content-center mt-3 small">
                <a href="/register" class="text-decoration-none">Create an account</a>
                <span class="text-muted">·</span>
                <a href="/pharmacy/login" class="text-decoration-none">Pharmacy portal</a>
                <span class="text-muted">·</span>
                <a href="/admin/login" class="text-decoration-none">Admin</a>
            </div>
        </div>
    </div>
</div>