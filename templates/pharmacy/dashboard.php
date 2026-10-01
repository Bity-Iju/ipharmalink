<?php

/**
 * Pharmacy dashboard — /pharmacy/dashboard
 *
 * @var array $stats
 * @var array $wallet
 * @var array $charts
 * @var array $topProducts
 * @var array $newOrders
 * @var array $lowStock
 * @var array $expiring
 * @var array $pharmacy
 */
$orders = $stats['orders'];
$products = $stats['products'];

$dailyLabels  = array_column($charts['daily'], 'day');
$dailyRevenue = array_map('floatval', array_column($charts['daily'], 'revenue'));
$dailyOrders  = array_map('intval', array_column($charts['daily'], 'orders'));

$monthLabels  = array_column($charts['months'], 'month');
$monthRevenue = array_map('floatval', array_column($charts['months'], 'revenue'));

$salesChart = json_encode([
    'type' => 'line',
    'data' => [
        'labels'   => $dailyLabels,
        'datasets' => [[
            'label'           => 'Revenue (₦)',
            'data'            => $dailyRevenue,
            'borderColor'     => '#0a7d5f',
            'backgroundColor' => 'rgba(10,125,95,.12)',
            'fill'            => true,
            'tension'         => 0.35,
            'pointRadius'     => 0,
            'borderWidth'     => 2,
        ]],
    ],
    'options' => [
        'responsive' => true,
        'plugins'    => ['legend' => ['display' => false]],
        'scales'     => ['y' => ['ticks' => ['callback' => 'function(v){return "₦" + v.toLocaleString();}']]],
    ],
], JSON_UNESCAPED_SLASHES);

$volumeChart = json_encode([
    'type' => 'bar',
    'data' => [
        'labels'   => $dailyLabels,
        'datasets' => [[
            'label'           => 'Orders',
            'data'            => $dailyOrders,
            'backgroundColor' => '#14b88a',
            'borderRadius'    => 4,
        ]],
    ],
    'options' => [
        'responsive' => true,
        'plugins'    => ['legend' => ['display' => false]],
        'scales'     => ['y' => ['beginAtZero' => true]],
    ],
], JSON_UNESCAPED_SLASHES);

$stockChart = json_encode([
    'type' => 'doughnut',
    'data' => [
        'labels' => ['In stock', 'Low stock', 'Out of stock', 'Expired'],
        'datasets' => [[
            'data' => [
                max(0, (int) $products['active'] - (int) $products['low_stock'] - (int) $products['out_of_stock']),
                (int) $products['low_stock'],
                (int) $products['out_of_stock'],
                (int) $products['expired'],
            ],
            'backgroundColor' => ['#0a7d5f', '#f59e0b', '#dc2626', '#94a3b8'],
        ]],
    ],
    'options' => ['responsive' => true, 'plugins' => ['legend' => ['position' => 'bottom']]],
], JSON_UNESCAPED_SLASHES);
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-currency-naira',
            'label' => "Today's sales",
            'value' => money((float) $stats['sales']['today']),
            'tone' => 'success',
            'link' => '/pharmacy/reports/sales',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-graph-up-arrow',
            'label' => 'This month',
            'value' => money_compact((float) $stats['sales']['month']),
            'tone' => 'primary',
            'link' => '/pharmacy/reports',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-bag-check',
            'label' => 'New orders',
            'value' => (int) $stats['todayOrders'],
            'hint' => $orders['paid'] + $orders['received'] . ' awaiting action',
            'tone' => 'warning',
            'link' => '/pharmacy/orders/new',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-people',
            'label' => 'Customers',
            'value' => number_format($stats['customers']),
            'hint' => '+' . (int) $stats['newCustomers'] . ' this month',
            'tone' => 'info',
            'link' => '/pharmacy/customers',
        ]); ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Total products',  (int) $products['total'],  'bi-box-seam',      'primary', '/pharmacy/products'],
        ['Active products',  (int) $products['active'],  'bi-check2-circle', 'success', '/pharmacy/products?status=active'],
        ['Low stock',        (int) $products['low_stock'], 'bi-exclamation-triangle', 'warning', '/pharmacy/inventory/low-stock'],
        ['Out of stock',     (int) $products['out_of_stock'], 'bi-x-circle', 'danger', '/pharmacy/inventory?status=out_of_stock'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-3">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => number_format($value),
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
                <h2 class="h6 fw-bold mb-3">Sales — last 14 days</h2>
                <div style="height:240px"><canvas data-chart="<?= e($salesChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Inventory status</h2>
                <div style="height:240px"><canvas data-chart="<?= e($stockChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Order volume — last 14 days</h2>
                <div style="height:200px"><canvas data-chart="<?= e($volumeChart) ?>"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Top products</h2>
                <?php if (empty($topProducts)): ?>
                    <div class="empty-state py-3">
                        <p class="mb-0 small">No sales recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td class="text-truncate"><?= e(str_excerpt((string) $product['product_name'], 42)) ?></td>
                                        <td class="text-end text-muted small"><?= (int) $product['units'] ?> sold</td>
                                        <td class="text-end fw-semibold small"><?= money_compact((float) $product['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Orders needing action</h2>
                    <a href="/pharmacy/orders/new" class="section-link">View all</a>
                </div>
                <?php if (empty($newOrders)): ?>
                    <div class="empty-state py-3">
                        <div class="icon"><i class="bi bi-check2-all"></i></div>
                        <p class="mb-0 small">You are all caught up.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table ipl-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Sub-order</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($newOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="/pharmacy/orders/<?= (int) $order['id'] ?>" class="fw-semibold small">
                                                <?= e((string) $order['sub_order_number']) ?>
                                            </a>
                                            <div class="text-muted small"><?= (int) $order['item_count'] ?> item(s)</div>
                                        </td>
                                        <td class="small"><?= e((string) $order['customer_name']) ?></td>
                                        <td class="fw-semibold small"><?= money((float) $order['total']) ?></td>
                                        <td><?= status_badge((string) $order['status']) ?></td>
                                        <td class="text-end">
                                            <a href="/pharmacy/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-primary">
                                                Open
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
    </div>

    <div class="col-xl-6">
        <div class="ipl-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Low stock</h2>
                    <a href="/pharmacy/inventory/low-stock" class="section-link">Manage</a>
                </div>
                <?php if (empty($lowStock)): ?>
                    <p class="small text-muted mb-0">Stock levels look healthy.</p>
                <?php else: ?>
                    <div class="d-grid gap-1">
                        <?php foreach (array_slice($lowStock, 0, 5) as $item): ?>
                            <div class="d-flex justify-content-between small py-1" style="border-bottom:1px dashed var(--ipl-border)">
                                <span class="text-truncate me-2"><?= e(str_excerpt((string) $item['name'], 40)) ?></span>
                                <span class="text-nowrap">
                                    <span class="badge bg-<?= (int) $item['stock_qty'] === 0 ? 'danger' : 'warning' ?>">
                                        <?= (int) $item['stock_qty'] ?> left
                                    </span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ipl-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0"><i class="bi bi-calendar-x text-danger me-1"></i>Expiry watchlist</h2>
                    <a href="/pharmacy/inventory/expiring" class="section-link">Manage</a>
                </div>
                <?php if (empty($expiring)): ?>
                    <p class="small text-muted mb-0">Nothing is expiring soon.</p>
                <?php else: ?>
                    <div class="d-grid gap-1">
                        <?php foreach (array_slice($expiring, 0, 5) as $item):
                            $days = (int) floor((strtotime((string) $item['expiry_date']) - time()) / 86400); ?>
                            <div class="d-flex justify-content-between small py-1" style="border-bottom:1px dashed var(--ipl-border)">
                                <span class="text-truncate me-2"><?= e(str_excerpt((string) $item['name'], 36)) ?></span>
                                <span class="text-nowrap text-muted">
                                    <?= $days < 0 ? 'Expired' : $days . ' days left' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ipl-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-wallet2 me-1"></i>Wallet</h2>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="text-muted small">Available</div>
                        <div class="fw-bold text-success"><?= money_compact((float) $wallet['balance']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Pending</div>
                        <div class="fw-bold text-warning"><?= money_compact((float) $wallet['pending_balance']) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Lifetime</div>
                        <div class="fw-bold"><?= money_compact((float) $wallet['total_earned']) ?></div>
                    </div>
                </div>
                <a href="/pharmacy/wallet" class="btn btn-light btn-sm w-100 mt-3">View wallet</a>
            </div>
        </div>
    </div>
</div>