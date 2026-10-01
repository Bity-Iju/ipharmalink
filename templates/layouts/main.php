<?php

/**
 * Public layout: storefront chrome (header, search, footer, toasts).
 *
 * @var string      $content   rendered page body
 * @var string      $title     page <title> (already suffixed by controllers)
 * @var string|null $metaDescription
 * @var array       $og        Open Graph overrides: title, description, image, url, type
 */
$iplUser     = \App\Auth::user();
$iplCart     = new \App\Services\CartService();
$iplCartCount = $iplCart->rawCount();
$iplUnread   = $iplUser ? \App\Services\NotificationService::unreadCount((int) $iplUser['id']) : 0;
$iplSettings = [
    'name'     => \App\Setting::getString('general.platform_name', \App\Config::str('app.name', 'iPharmaLink')),
    'phone'    => \App\Setting::getString('general.support_phone', ''),
    'email'    => \App\Setting::getString('general.support_email', ''),
    'address'  => \App\Setting::getString('general.address', ''),
];
$og = $og ?? [];
$metaDescription = $metaDescription ?? \App\Setting::getString('general.meta_description', 'Order medicines from verified pharmacies near you with delivery or pickup on ' . $iplSettings['name'] . '.');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $iplSettings['name']) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="canonical" href="<?= e(url(\App\Router::currentPath())) ?>">

    <meta property="og:site_name" content="<?= e($iplSettings['name']) ?>">
    <meta property="og:title" content="<?= e($og['title'] ?? ($title ?? $iplSettings['name'])) ?>">
    <meta property="og:description" content="<?= e($og['description'] ?? $metaDescription) ?>">
    <meta property="og:type" content="<?= e($og['type'] ?? 'website') ?>">
    <meta property="og:url" content="<?= e($og['url'] ?? url(\App\Router::currentPath())) ?>">
    <?php if (!empty($og['image'])): ?>
        <meta property="og:image" content="<?= e(upload_url($og['image'])) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?= $head ?? '' ?>
</head>

<body data-base="<?= e(rtrim((string) \App\Config::str('app.url'), '/')) ?>">

    <?= \App\View::capture('components/flashes') ?>

    <?php \App\View::include('components/header', [
        'cartCount' => $iplCartCount,
        'unread'    => $iplUnread,
        'user'      => $iplUser,
        'settings'  => $iplSettings,
    ]); ?>

    <main><?= $content ?></main>

    <?php \App\View::include('components/footer', ['settings' => $iplSettings]); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?= $scripts ?? '' ?>
</body>

</html>