<?php

/**
 * Delivery rider sidebar.
 */
$path = \App\Router::currentPath();

$link = static function (string $url, string $icon, string $label, ?array $badge = null) use ($path): string {
    $active = $path === $url || str_starts_with($path, rtrim($url, '/') . '/');
    if ($url === '/delivery/dashboard' && $path !== $url) {
        $active = false;
    }
    return sprintf(
        '<a class="nav-link%s" href="%s"><i class="bi %s"></i><span>%s</span>%s</a>',
        $active ? ' active' : '',
        e($url),
        e($icon),
        e($label),
        $badge !== null
            ? '<span class="badge rounded-pill bg-' . e($badge['tone']) . '">' . (int) $badge['count'] . '</span>'
            : ''
    );
};

$section = static fn(string $label): string => '<div class="nav-section">' . e($label) . '</div>';

$activeCount = (int) \App\Database::instance()->value(
    'SELECT COUNT(*) FROM deliveries
     WHERE personnel_id = ? AND status IN ("assigned","picked_up","in_transit")',
    ['p' => \App\Auth::id()]
);
?>
<a class="nav-link<?= $path === '/delivery/dashboard' ? ' active' : '' ?>" href="/delivery/dashboard">
    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
</a>

<?= $section('Work') ?>
<?= $link('/delivery/dashboard', 'bi-list-task', 'Active drops', $activeCount > 0 ? ['tone' => 'warning', 'count' => $activeCount] : null) ?>
<?= $link('/delivery/orders', 'bi-receipt', 'All deliveries') ?>
<?= $link('/delivery/history', 'bi-clock-history', 'History') ?>

<?= $section('Account') ?>
<?= $link('/delivery/profile', 'bi-person', 'My profile') ?>

<div class="nav-section">Support</div>
<a class="nav-link" href="/contact">
    <i class="bi bi-headset"></i><span>Contact dispatch</span>
</a>