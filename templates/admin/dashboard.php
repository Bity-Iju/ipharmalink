<?php

/**
 * Super admin dashboard — /admin/dashboard
 *
 * @var array $users
 * @var array $pharmacies
 * @var array $catalog
 * @var array $orders
 * @var array $finance
 * @var array $commission
 * @var array $payouts
 * @var float $walletLiabilities
 * @var array $charts
 * @var array $pendingPharmacies
 * @var array $recentOrders
 * @var array $lowStock
 * @var array $alerts
 */
$dailyLabels  = array_column($charts['daily'], 'day');
$dailyRevenue = array_map('floatval', array_column($charts['daily'], 'revenue'));
$monthLabels  = array_column($charts['monthly'], 'month');
$monthRevenue = array_map('floatval', array_column($charts['monthly'], 'revenue'));

$revenueChart = json_encode([
    'type' => 'line',
    'data' => [
        'labels'   => $dailyLabels,
        'datasets' => [[
            'label' => 'Revenue (₦)',
            'data' => $dailyRevenue,
            'borderColor' => '#0a7d5f',
            'backgroundColor' => 'rgba(10,125,95,.12)',
            'fill' => true,
            'tension' => 0.35,
            'pointRadius' => 0,
            'borderWidth' => 2,
        ]],
    ],
    'options' => [
        'responsive' => true,
        'plugins'    => ['legend' => ['display' => false]],
        'scales'     => ['y' => ['ticks' => ['callback' => 'function(v){return "₦" + v.toLocaleString();}']]],
    ],
], JSON_UNESCAPED_SLASHES);

$orderChart = json_encode([
    'type' => 'bar',
    'data' => [
        'labels'   => $monthLabels,
        'datasets' => [[
            'label' => 'Orders',
            'data' => array_map('intval', array_column($charts['monthly'], 'orders')),
            'backgroundColor' => '#14b88a',
            'borderRadius' => 4,
        ]],
    ],
    'options' => ['responsive' => true, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true]]],
], JSON_UNESCAPED_SLASHES);

$pharmacyChart = json_encode([
    'type' => 'bar',
    'data' => [
        'labels'   => ['Approved', 'Pending', 'Suspended', 'Rejected'],
        'datasets' => [[
            'data' => [
                (int) $pharmacies['approved'],
                (int) $pharmacies['pending'],
                (int) $pharmacies['suspended'],
                (int) $pharmacies['rejected'],
            ],
            'backgroundColor' => ['#0a7d5f', '#f59e0b', '#dc2626', '#94a3b8'],
        ]],
    ],
    'options' => ['responsive' => true, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true]]],
], JSON_UNESCAPED_SLASHES);
?>
<!-- Action required -->
<?php if ((int) $pharmacies['pending'] > 0 || $alerts['failedPayments'] > 0 || $alerts['openRefunds'] > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="flex-grow-1">
            <strong>Needs your attention.</strong>
            <?php
            $bits = [];
            if ((int) $pharmacies['pending'] > 0) {
                $bits[] = sprintf('%d pharmacy registration(s) awaiting review', (int) $pharmacies['pending']);
            }
            if ($alerts['openRefunds'] > 0) {
                $bits[] = sprintf('%d open refund(s)', (int) $alerts['openRefunds']);
            }
            if ($alerts['unreadMessages'] > 0) {
                $bits[] = sprintf('%d unread contact message(s)', (int) $alerts['unreadMessages']);
            }
            echo e(implode(' · ', $bits));
            ?>
        </div>
        <?php if ((int) $pharmacies['pending'] > 0): ?>
            <a href="/admin/pharmacies/pending" class="btn btn-sm btn-primary">Review now</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Customers',    number_format((int) $users['customers']),        'bi-people',       'primary', '/admin/customers'],
        ['Pharmacies',   number_format((int) $pharmacies['total']),       'bi-shop',         'success', '/admin/pharmacies'],
        ['Products',     number_format((int) $catalog['products']),       'bi-box-seam',     'info',    '/admin/products'],
        ['Orders (all)', number_format((int) $orders['total']),           'bi-receipt',      'warning', '/admin/orders'],
        ['Gross revenue', money_compact((float) $finance['gross_revenue']), 'bi-currency-naira', 'success', '/admin/reports/sales'],
        ['Commission',   money_compact((float) $commission['credited']),  'bi-percent',      'info',    '/admin/commissions'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-2">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
                'link' => $link,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Revenue — last 30 days</h2>
                <div style="height:250px"><canvas data-chart="<?= e($revenueChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Pharmacy status</h2>
                <div style="height:250px"><canvas data-chart="<?= e($pharmacyChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Order volume — last 12 months</h2>
                <div style="height:210px"><canvas data-chart="<?= e($orderChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Finance</h2>
                <div class="small">
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <span class="text-muted">Revenue today</span><strong><?= money((float) $finance['today']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <span class="text-muted">Revenue this month</span><strong><?= money((float) $finance['month']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <span class="text-muted">Commission earned</span>
                        <strong class="text-success"><?= money((float) $commission['credited']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <span class="text-muted">Commission pending</span>
                        <strong class="text-warning"><?= money((float) $commission['pending']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <span class="text-muted">Payouts outstanding</span>
                        <strong><?= money((float) $payouts['unpaid']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Pharmacy wallet liability</span>
                        <strong><?= money($walletLiabilities) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Pending pharmacy verification</h2>
                    <a href="/admin/pharmacies/pending" class="section-link">View all</a>
                </div>
                <?php if (empty($pendingPharmacies)): ?>
                    <p class="small text-muted mb-0">No pharmacies are waiting for review.</p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($pendingPharmacies as $pharmacy): ?>
                            <a href="/admin/pharmacies/<?= (int) $pharmacy['id'] ?>"
                                class="d-flex justify-content-between align-items-center p-2 rounded"
                                style="background:#f6f9f8;text-decoration:none;color:inherit">
                                <div class="min-w-0">
                                    <div class="small fw-semibold text-truncate"><?= e((string) $pharmacy['name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem">
                                        <?= e((string) $pharmacy['city']) ?>, <?= e((string) $pharmacy['state']) ?>
                                        · <?= e(date('j M Y', strtotime((string) $pharmacy['created_at']))) ?>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Recent orders</h2>
                    <a href="/admin/orders" class="section-link">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td class="small">
                                        <a href="/admin/orders/<?= (int) $order['id'] ?>" class="fw-semibold">
                                            <?= e((string) $order['order_number']) ?>
                                        </a>
                                        <div class="text-muted small"><?= (int) $order['pharmacies'] ?> pharmacy(ies)</div>
                                    </td>
                                    <td class="small"><?= e((string) $order['customer_name']) ?></td>
                                    <td class="text-end small fw-semibold"><?= money((float) $order['total']) ?></td>
                                    <td><?= status_badge((string) $order['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Catalogue</h2>
                <div class="small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Total products</span><strong><?= number_format((int) $catalog['products']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Active</span><strong><?= number_format((int) $catalog['active']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Awaiting approval</span>
                        <strong class="<?= (int) $catalog['awaiting_approval'] > 0 ? 'text-warning' : '' ?>">
                            <?= (int) $catalog['awaiting_approval'] ?>
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Out of stock</span>
                        <strong class="<?= (int) $catalog['out_of_stock'] > 0 ? 'text-danger' : '' ?>">
                            <?= (int) $catalog['out_of_stock'] ?>
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Expired</span>
                        <strong class="<?= (int) $catalog['expired'] > 0 ? 'text-danger' : '' ?>">
                            <?= (int) $catalog['expired'] ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Orders</h2>
                <div class="small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Today</span><strong><?= (int) $orders['today'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">This month</span><strong><?= (int) $orders['month'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Open</span><strong class="text-info"><?= (int) $orders['open'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Delivered</span><strong class="text-success"><?= (int) $orders['delivered'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Cancelled</span><strong><?= (int) $orders['cancelled'] ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Stock warnings</h2>
                <?php if (empty($lowStock)): ?>
                    <p class="small text-muted mb-0">Stock levels look healthy across the platform.</p>
                <?php else: ?>
                    <div class="d-grid gap-1">
                        <?php foreach ($lowStock as $item): ?>
                            <div class="d-flex justify-content-between small py-1" style="border-bottom:1px dashed var(--ipl-border)">
                                <span class="text-truncate me-2">
                                    <?= e(str_excerpt((string) $item['name'], 28)) ?>
                                    <span class="text-muted">· <?= e(str_excerpt((string) $item['pharmacy_name'], 16)) ?></span>
                                </span>
                                <span class="badge bg-<?= (int) $item['stock_qty'] === 0 ? 'danger' : 'warning' ?>">
                                    <?= (int) $item['stock_qty'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>