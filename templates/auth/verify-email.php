<?php

/**
 * Email verification notice — /verify-email
 *
 * @var array $user
 * @var string $masked
 */
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-body">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">
                    <i class="bi bi-envelope-check"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Verify your email</h1>
                <p class="text-muted small mb-0">One quick step to secure your account</p>
            </div>

            <p class="small">
                We sent a verification link to <strong><?= e($masked) ?></strong>.
                Open it to confirm your address and receive order updates by email.
            </p>

            <p class="small text-muted">
                Check your spam folder if it has not arrived within a couple of minutes.
            </p>

            <form method="post" action="/resend-verification" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-soft w-100">Send the link again</button>
            </form>

            <div class="text-center mt-3 small">
                <a href="/account">Continue to my account anyway</a>
            </div>
        </div>
    </div>
</div>