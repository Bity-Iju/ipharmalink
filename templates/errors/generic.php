<?php
/**
 * Generic error page, rendered by ErrorHandler for every HTTP status.
 *
 * Each templates/errors/{status}.php requires this file, so the copy and
 * styling for all statuses live in one place.
 *
 * @var int         $status
 * @var string      $errorTitle    from Response::statusTitle()
 * @var string|null $errorDetail   only set when APP_DEBUG=true
 * @var string|null $safeMessage
 */
$messages = [
    400 => 'That request could not be understood. Please check the details and try again.',
    401 => 'You need to sign in to see that page.',
    403 => 'You do not have permission to view this page. If you think this is wrong, sign in with a different account or contact support.',
    404 => 'We could not find the page you were looking for. It may have been moved, or the link may be incorrect.',
    419 => 'Your session expired while the page was open. Please refresh and try again — nothing was lost.',
    429 => 'You have made too many requests in a short time. Please wait a few minutes before trying again.',
    500 => 'Something went wrong on our side. Our team has been notified — please try again shortly.',
    503 => 'The platform is temporarily down for maintenance. We will be back shortly.',
];

$message = $messages[$status] ?? 'An unexpected error occurred.';

$favicon = defined('APP_ROOT') && is_file(APP_ROOT . '/assets/images/favicon.svg')
    ? '/assets/images/favicon.svg?v=' . filemtime(APP_ROOT . '/assets/images/favicon.svg')
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($errorTitle ?? 'Error ' . $status) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f6f9f8; }
        .err-wrap { min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem; }
        .err-code { font-size: clamp(4rem, 14vw, 8rem); font-weight: 800; line-height: 1; color: #0a7d5f; }
        .err-card { background: #fff; border: 1px solid #e3ebe8; border-radius: 20px; padding: 2.5rem; max-width: 560px; }
    </style>
</head>
<body>
<div class="err-wrap">
    <div class="err-card text-center">
        <div class="err-code"><?= (int) $status ?></div>
        <h1 class="h4 fw-bold mb-2"><?= e($errorTitle ?? 'Something went wrong') ?></h1>
        <p class="text-muted mb-4"><?= e($safeMessage ?: $message) ?></p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="/" class="btn btn-primary" style="background:#0a7d5f;border-color:#0a7d5f">Back to homepage</a>
            <a href="/contact" class="btn btn-light">Contact support</a>
            <?php if ($status === 401 || $status === 403): ?>
                <a href="/login" class="btn btn-light">Sign in</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($errorDetail)): ?>
            <details class="mt-4 text-start">
                <summary class="small text-muted">Technical details (shown because debug mode is on)</summary>
                <pre class="small mt-2 p-2 bg-light rounded" style="white-space:pre-wrap;word-break:break-word"><?= e((string) $errorDetail) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
