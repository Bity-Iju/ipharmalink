<?php
/** @var array $errors */
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-boxes"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Wholesale supplier portal</h1>
                <p class="text-muted small mb-0">Sign in to manage your verified supplier account</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">
                    <?php foreach ($errors as $messages): ?>
                        <?php foreach ($messages as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/supplier/login" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="identifier">Email or phone</label>
                    <input type="text" name="identifier" id="identifier" class="form-control" required autocomplete="username" autofocus value="<?= e(old('identifier')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/supplier/register">Register as a supplier</a>
                <div class="text-muted mt-1"><a href="/pharmacy/login">Pharmacy sign in</a></div>
            </div>
        </div>
    </div>
</div>