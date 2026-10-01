<?php

/**
 * Admin sidebar.
 *
 * @var array|null $pharmacy  unused here, kept for a consistent signature
 */
$path = \App\Router::currentPath();

/**
 * Renders one sidebar entry, marking the current section active.
 *
 * $badge accepts either null or ['tone' => 'warning', 'count' => 3].
 */
$link = static function (string $url, string $icon, string $label, ?array $badge = null) use ($path): string {
    $active = $url === '/admin/dashboard'
        ? $path === $url
        : ($path === $url || str_starts_with($path, rtrim($url, '/') . '/'));

    return sprintf(
        '<a class="nav-link%s" href="%s">%s<span>%s</span>%s</a>',
        $active ? ' active' : '',
        e($url),
        '<i class="bi ' . e($icon) . '"></i>',
        e($label),
        $badge !== null
            ? '<span class="badge rounded-pill bg-' . e($badge['tone']) . '">' . (int) $badge['count'] . '</span>'
            : ''
    );
};

$section = static function (string $label): string {
    return '<div class="nav-section">' . e($label) . '</div>';
};

$db = \App\Database::instance();
$pendingPharmacies = (int) $db->value("SELECT COUNT(*) FROM pharmacies WHERE status = 'pending' AND deleted_at IS NULL");
$pendingSuppliers = (int) $db->value("SELECT COUNT(*) FROM suppliers WHERE status IN ('pending','under_review') AND deleted_at IS NULL");
$openOrders       = (int) $db->value("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled','refunded')");
$pendingPayouts   = (int) $db->value("SELECT COUNT(*) FROM payouts WHERE status = 'requested'");
$openRefunds      = (int) $db->value("SELECT COUNT(*) FROM refunds WHERE status IN ('pending','processing')");
$awaitingProducts = (int) $db->value('SELECT COUNT(*) FROM products WHERE is_approved = 0 AND deleted_at IS NULL');
$newMessages      = (int) $db->value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$failedPayments   = (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'failed'");
?>
<div class="nav-link d-flex align-items-center gap-2 py-3">
    <a class="nav-section text-decoration-none d-block w-100 m-0" href="/admin/dashboard">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>
</div>

<?= $section('Marketplace') ?>
<?= $link('/admin/pharmacies', 'bi-shop', 'Pharmacies', $pendingPharmacies > 0 ? ['tone' => 'warning', 'count' => $pendingPharmacies] : null) ?>
<?= $link('/admin/suppliers', 'bi-boxes', 'Wholesale suppliers', $pendingSuppliers > 0 ? ['tone' => 'warning', 'count' => $pendingSuppliers] : null) ?>
<?= $link('/admin/products', 'bi-box-seam', 'Products', $awaitingProducts > 0 ? ['tone' => 'warning', 'count' => $awaitingProducts] : null) ?>
<?= $link('/admin/categories', 'bi-tags', 'Categories') ?>
<?= $link('/admin/brands', 'bi-award', 'Brands') ?>

<?= $section('Orders') ?>
<?= $link('/admin/orders', 'bi-receipt', 'All orders', $openOrders > 0 ? ['tone' => 'info', 'count' => $openOrders] : null) ?>
<?= $link('/admin/orders/pending', 'bi-hourglass-split', 'Pending') ?>
<?= $link('/admin/orders/processing', 'bi-box-seam', 'Processing') ?>
<?= $link('/admin/orders/delivered', 'bi-check2-circle', 'Delivered') ?>
<?= $link('/admin/orders/cancelled', 'bi-x-circle', 'Cancelled') ?>

<?= $section('Users') ?>
<?= $link('/admin/customers', 'bi-people', 'Customers') ?>
<?= $link('/admin/staff', 'bi-person-badge', 'Pharmacy Owners & Staff') ?>
<?= $link('/admin/delivery-personnel', 'bi-bicycle', 'Delivery Personnel') ?>

<?= $section('Finance') ?>
<?= $link('/admin/payments', 'bi-credit-card', 'Payments', $failedPayments > 0 ? ['tone' => 'danger', 'count' => $failedPayments] : null) ?>
<?= $link('/admin/refunds', 'bi-arrow-counterclockwise', 'Refunds', $openRefunds > 0 ? ['tone' => 'warning', 'count' => $openRefunds] : null) ?>
<?= $link('/admin/commissions', 'bi-percent', 'Commissions') ?>
<?= $link('/admin/wallets', 'bi-wallet2', 'Pharmacy Wallets') ?>
<?= $link('/admin/payouts', 'bi-cash-stack', 'Payouts', $pendingPayouts > 0 ? ['tone' => 'warning', 'count' => $pendingPayouts] : null) ?>

<?= $section('Marketing') ?>
<?= $link('/admin/coupons', 'bi-tag', 'Coupons') ?>

<?= $section('Content') ?>
<?= $link('/admin/banners', 'bi-images', 'Banners') ?>
<?= $link('/admin/pages', 'bi-file-earmark-richtext', 'Pages') ?>
<?= $link('/admin/faqs', 'bi-question-circle', 'FAQs') ?>
<?= $link('/admin/contact-messages', 'bi-chat-left-dots', 'Contact Messages', $newMessages > 0 ? ['tone' => 'info', 'count' => $newMessages] : null) ?>

<?= $section('Oversight') ?>
<?= $link('/admin/reviews', 'bi-star', 'Reviews') ?>
<?= $link('/admin/reports', 'bi-graph-up', 'Reports') ?>
<?= $link('/admin/notifications', 'bi-bell', 'Notifications') ?>
<?= $link('/admin/audit-logs', 'bi-shield-check', 'Audit Logs') ?>

<?= $section('Settings') ?>
<?= $link('/admin/settings', 'bi-gear', 'General') ?>
<?= $link('/admin/payment-settings', 'bi-credit-card-2-front', 'Payments') ?>
<?= $link('/admin/delivery-settings', 'bi-truck', 'Delivery') ?>
<?= $link('/admin/email-settings', 'bi-envelope', 'Email') ?>
<?= $link('/admin/security-settings', 'bi-shield-lock', 'Security') ?>

<div class="nav-section">Storefront</div>
<a class="nav-link" href="/" target="_blank" rel="noopener">
    <i class="bi bi-box-arrow-up-right"></i><span>View marketplace</span>
</a>