<?php

/**
 * Pharmacy dashboard sidebar.
 *
 * @var array $pharmacy
 */
$path = \App\Router::currentPath();

$link = static function (string $url, string $icon, string $label, ?array $badge = null) use ($path): string {
    $active = $path === $url || str_starts_with($path, rtrim($url, '/') . '/');
    if ($url === '/pharmacy/dashboard' && $path !== $url) {
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

$pharmacyId = \App\Auth::pharmacyId();
$db         = \App\Database::instance();

$newOrders   = (int) $db->value(
    "SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = ? AND status IN ('paid','received')",
    ['id' => $pharmacyId]
);
$preparing   = (int) $db->value(
    "SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = ? AND status IN ('processing','preparing')",
    ['id' => $pharmacyId]
);
$ready       = (int) $db->value(
    "SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = ? AND status IN ('ready_for_pickup','ready_for_delivery')",
    ['id' => $pharmacyId]
);
$pendingRx   = (int) $db->value(
    "SELECT COUNT(*) FROM prescriptions WHERE pharmacy_id = ? AND status = 'pending'",
    ['id' => $pharmacyId]
);
$lowStock    = (int) $db->value(
    'SELECT COUNT(*) FROM products WHERE pharmacy_id = ? AND deleted_at IS NULL
       AND is_active = 1 AND stock_qty <= min_stock_level',
    ['id' => $pharmacyId]
);
$expiring    = (int) $db->value(
    'SELECT COUNT(*) FROM products WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1
       AND expiry_date IS NOT NULL AND expiry_date <= CURDATE() + INTERVAL 90 DAY',
    ['id' => $pharmacyId]
);
$isOwner     = \App\Auth::isPharmacyOwner();
?>
<a class="nav-link<?= $path === '/pharmacy/dashboard' ? ' active' : '' ?>" href="/pharmacy/dashboard">
    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
</a>

<?= $section('Orders') ?>
<?= $link('/pharmacy/orders/new', 'bi-bell', 'New Orders', $newOrders > 0 ? ['tone' => 'warning', 'count' => $newOrders] : null) ?>
<?= $link('/pharmacy/orders', 'bi-receipt', 'All Orders') ?>
<?= $link('/pharmacy/orders/processing', 'bi-box-seam', 'Processing', $preparing > 0 ? ['tone' => 'info', 'count' => $preparing] : null) ?>
<?= $link('/pharmacy/orders/ready', 'bi-bag-check', 'Ready', $ready > 0 ? ['tone' => 'success', 'count' => $ready] : null) ?>
<?= $link('/pharmacy/orders/delivered', 'bi-check2-circle', 'Delivered') ?>
<?= $link('/pharmacy/orders/cancelled', 'bi-x-circle', 'Cancelled') ?>
<?= $link('/pharmacy/prescriptions', 'bi-file-earmark-medical', 'Prescriptions', $pendingRx > 0 ? ['tone' => 'warning', 'count' => $pendingRx] : null) ?>

<?= $section('Products') ?>
<?= $link('/pharmacy/products', 'bi-box-seam', 'All Products') ?>
<?= $link('/pharmacy/products/create', 'bi-plus-circle', 'Add Product') ?>

<?= $section('Inventory') ?>
<?= $link('/pharmacy/inventory', 'bi-clipboard-data', 'Stock') ?>
<?= $link('/pharmacy/inventory/low-stock', 'bi-exclamation-triangle', 'Low Stock', $lowStock > 0 ? ['tone' => 'warning', 'count' => $lowStock] : null) ?>
<?= $link('/pharmacy/inventory/expiring', 'bi-calendar-x', 'Expiring', $expiring > 0 ? ['tone' => 'danger', 'count' => $expiring] : null) ?>
<?= $link('/pharmacy/inventory/history', 'bi-clock-history', 'Stock History') ?>
<?= $link('/pharmacy/inventory/adjust', 'bi-sliders', 'Adjust Stock') ?>

<?= $section('Customers') ?>
<?= $link('/pharmacy/customers', 'bi-people', 'My Customers') ?>
<?= $link('/pharmacy/reviews', 'bi-star', 'Reviews') ?>

<?= $section('Fulfilment') ?>
<?= $link('/pharmacy/deliveries', 'bi-truck', 'Deliveries') ?>
<?php if ($isOwner): ?>
    <?= $link('/pharmacy/staff', 'bi-person-badge', 'Staff') ?>
    <?= $link('/pharmacy/wallet', 'bi-wallet2', 'Wallet') ?>
    <?= $link('/pharmacy/payouts', 'bi-cash-stack', 'Payouts') ?>
<?php endif; ?>

<?= $section('Insight') ?>
<?= $link('/pharmacy/reports', 'bi-graph-up', 'Reports') ?>
<?= $link('/pharmacy/notifications', 'bi-bell', 'Notifications') ?>
<?= $link('/pharmacy/settings', 'bi-gear', 'Settings') ?>

<div class="nav-section">Storefront</div>
<a class="nav-link" href="/pharmacy/<?= e((string) ($pharmacy['slug'] ?? '')) ?>" target="_blank" rel="noopener">
    <i class="bi bi-box-arrow-up-right"></i><span>View my storefront</span>
</a>