<?php

/**
 * Dashboard layout used by the admin, pharmacy and delivery modules.
 * Expects: $content, $title, plus optional $sidebar and $breadcrumbs.
 *
 * @var string      $content
 * @var string      $title
 * @var string|null $sidebar   nav items (already rendered markup)
 * @var array       $breadcrumbs [['label' => '…', 'url' => '…'], …]
 */
$iplUser     = \App\Auth::user();
$iplUnread   = $iplUser ? \App\Services\NotificationService::unreadCount((int) $iplUser['id']) : 0;
$breadcrumbs = $breadcrumbs ?? [];
$homeUrl = match (true) {
    \App\Auth::isSuperAdmin()    => '/admin/dashboard',
    \App\Auth::isPharmacy()      => '/pharmacy/dashboard',
    \App\Auth::isDelivery()      => '/delivery/dashboard',
    \App\Auth::isSupplier()     => '/supplier/dashboard',
    default                      => '/account',
};
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> · <?= e(\App\Config::str('app.name')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?= $head ?? '' ?>
</head>

<body data-base="<?= e(\App\Config::baseUrl()) ?>">

    <?= \App\View::capture('components/flashes') ?>

    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="brand">
                <a href="<?= e($homeUrl) ?>" class="text-white d-flex align-items-center gap-2">
                    <span class="brand-mark"><i class="bi bi-capsule-pill"></i></span>
                    <?= e(\App\Config::str('app.name')) ?>
                </a>
            </div>
            <?= $sidebar ?? '' ?>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button class="btn btn-sm btn-light sidebar-toggle" type="button" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="h5 mb-0 flex-grow-1"><?= e($pageHeading ?? ($title ?? 'Dashboard')) ?></h1>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= e(\App\Auth::isSuperAdmin() ? '/admin/notifications' : (\App\Auth::isPharmacy() ? '/pharmacy/notifications' : '/account/notifications')) ?>"
                        class="btn btn-sm btn-light position-relative" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($iplUnread > 0): ?>
                            <span class="cart-count"><?= (int) $iplUnread ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown">
                            <span class="brand-mark" style="width:28px;height:28px;font-size:.8rem">
                                <i class="bi bi-person"></i>
                            </span>
                            <span class="d-none d-md-inline"><?= e(strtok((string) ($iplUser['full_name'] ?? 'Account'), ' ')) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-2">
                                <div class="fw-semibold small"><?= e($iplUser['full_name'] ?? '') ?></div>
                                <div class="text-muted" style="font-size:.76rem"><?= e($iplUser['email'] ?? '') ?></div>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= e($homeUrl) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= e(\App\Auth::isSuperAdmin() ? '/admin/settings' : '/pharmacy/profile') ?>">
                                    <i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form method="post" action="/logout" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sign out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="app-content">
                <?php if ($breadcrumbs !== []): ?>
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= e($homeUrl) ?>">Home</a></li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <?php if (!empty($crumb['url'])): ?>
                                    <li class="breadcrumb-item"><a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>

                <?= $content ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?= $scripts ?? '' ?>
</body>

</html>