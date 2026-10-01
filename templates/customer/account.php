<?php

/**
 * Customer account overview — /account
 *
 * @var array $user
 * @var array $stats
 * @var array $recentOrders
 * @var array $wishlist
 * @var int   $cartCount
 */
$nav = [
    ['/account', 'bi-person', 'Overview'],
    ['/account/profile', 'bi-person-gear', 'Profile'],
    ['/account/orders', 'bi-receipt', 'My Orders'],
    ['/account/addresses', 'bi-geo-alt', 'Addresses'],
    ['/account/wishlist', 'bi-heart', 'Wishlist'],
    ['/account/reviews', 'bi-star', 'My Reviews'],
    ['/account/notifications', 'bi-bell', 'Notifications'],
    ['/account/change-password', 'bi-key', 'Change Password'],
];
$current = \App\Router::currentPath();
?>
<div class="row g-4">
    <div class="col-lg-3">
        <div class="ipl-card p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-3">
                <img src="<?= e(upload_url($user['profile_image'])) ?>" alt="" width="48" height="48"
                    class="rounded-circle" style="object-fit:cover" onerror="this.src='/assets/images/placeholder.svg'">
                <div class="min-w-0">
                    <div class="fw-bold text-truncate"><?= e((string) $user['full_name']) ?></div>
                    <div class="small text-muted text-truncate"><?= e((string) $user['email']) ?></div>
                </div>
            </div>
            <ul class="nav nav-pills flex-column gap-1">
                <?php foreach ($nav as [$url, $icon, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $current === $url ? ' active' : '' ?>" href="<?= e($url) ?>"
                            style="color:var(--ipl-ink);<?= $current === $url ? 'background:var(--ipl-primary-light);color:var(--ipl-primary-dark);font-weight:600' : '' ?>">
                            <i class="bi <?= e($icon) ?> me-2"></i><?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form method="post" action="/logout" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-light w-100 text-danger"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button>
        </form>
    </div>

    <div class="col-lg-9">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0">Hello, <?= e(strtok((string) $user['full_name'], ' ')) ?> 👋</h2>
            <?php if ($user['email_verified_at'] === null): ?>
                <a href="/verify-email" class="btn btn-sm btn-warning">
                    <i class="bi bi-envelope-exclamation me-1"></i>Verify your email
                </a>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <?php \App\View::include('components/stat-tile', [
                    'icon' => 'bi-receipt', 'label' => 'Total orders', 'value' => number_format((float) $stats['total_orders']),
                    'tone' => 'primary', 'link' => '/account/orders',
                ]); ?>
            </div>
                <?php \App\View::include('components/stat-tile', [
                    'icon' => 'bi-truck',
                    'label' => 'In progress',
                    'value' => number_format((float) $stats['active']),
                    'tone' => 'info',
                    'link' => '/account/orders',
                ]); ?>
            </div>
            <div class="col-6 col-lg-3">
                <?php \App\View::include('components/stat-tile', [
                    'icon' => 'bi-check2-circle',
                    'label' => 'Delivered',
                    'value' => number_format((float) $stats['delivered']),
                    'tone' => 'success',
                    'link' => '/account/orders?status=delivered',
                ]); ?>
            </div>
            <div class="col-6 col-lg-3">
                <?php \App\View::include('components/stat-tile', [
                    'icon' => 'bi-wallet2',
                    'label' => 'Total spent',
                    'value' => money_compact((float) $stats['spent']),
                    'tone' => 'warning',
                    'link' => '/account/orders',
                ]); ?>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="ipl-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">Recent orders</h3>
                    <a href="/account/orders" class="section-link">View all <i class="bi bi-arrow-right"></i></a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <div class="empty-state py-3">
                        <div class="icon"><i class="bi bi-receipt"></i></div>
                        <p class="mb-3">You have not placed an order yet.</p>
                        <a href="/products" class="btn btn-primary btn-sm">Start shopping</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table ipl-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Pharmacy</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="/account/orders/<?= (int) $order['id'] ?>" class="fw-semibold">
                                                <?= e((string) $order['order_number']) ?>
                                            </a>
                                            <div class="text-muted small"><?= (int) $order['item_count'] ?> item(s)</div>
                                        </td>
                                        <td class="small"><?= e(str_excerpt((string) $order['pharmacy_names'], 40)) ?></td>
                                        <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $order['created_at']))) ?></td>
                                        <td class="fw-semibold"><?= money((float) $order['total']) ?></td>
                                        <td><?= status_badge((string) $order['status']) ?></td>
                                        <td class="text-end">
                                            <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-light">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Wishlist preview -->
        <div class="ipl-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">Saved for later</h3>
                    <a href="/account/wishlist" class="section-link">View wishlist <i class="bi bi-arrow-right"></i></a>
                </div>

                <?php if (empty($wishlist)): ?>
                    <div class="empty-state py-3">
                        <div class="icon"><i class="bi bi-heart"></i></div>
                        <p class="mb-0">Tap the heart on any product to save it here.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($wishlist as $product): ?>
                            <div class="col-6 col-md-3">
                                <?php \App\View::include('components/product-card', ['product' => $product, 'compact' => true]); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>