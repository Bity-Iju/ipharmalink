<?php

/**
 * Administrator sign-in — /admin/login
 *
 * @var array $errors
 */
?>
<div class="auth-wrap" style="background:linear-gradient(160deg,#0b1f1a,#132f28)">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-shield-lock"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Administrator sign in</h1>
                <p class="text-muted small mb-0"><?= e(\App\Config::str('app.name')) ?> control panel</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">
                    <?php foreach ($errors as $messages): ?>
                        <?php foreach ($messages as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/admin/login" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="identifier">Email or phone</label>
                    <input type="text" name="identifier" id="identifier" class="form-control" required
                        autocomplete="username" autofocus value="<?= e(old('identifier')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required
                            autocomplete="current-password">
                        <button class="btn btn-light" type="button" data-toggle-password aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/login">Customer sign in</a> · <a href="/pharmacy/login">Pharmacy portal</a>
            </div>
        </div>
    </div>
</div>