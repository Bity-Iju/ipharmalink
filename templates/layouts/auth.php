<?php

/**
 * Bare layout for sign-in, registration and password-reset screens.
 *
 * @var string $content
 * @var string $title
 */
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sign in') ?> · <?= e(\App\Config::str('app.name')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>

<body data-base="<?= e(\App\Config::baseUrl()) ?>">

    <?= \App\View::capture('components/flashes') ?>

    <?= $content ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>

</html>