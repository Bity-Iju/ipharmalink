<?php

/**
 * Pharmacy reports overview — /pharmacy/reports
 *
 * @var array $summary
 * @var array $byStatus
 * @var array $products
 * @var array $lowStock
 * @var array $expiring
 * @var string $from
 * @var string $to
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Reports</h2>
    <form method="get" class="d-flex gap-2 align-items-center">
        <label class="small text-muted" for="from">From</label>
        <input type="date" name="from" id="from" class="form-control form-control-sm" value="<?= e($from) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" name="to" id="to" class="form-control form-control-sm" value="<?= e($to) ?>">
        <button class="btn btn-sm btn-primary">Apply</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Orders',       number_format((float) $summary['orders']),        'bi-receipt',       'primary'],
        ['Revenue',      money((float) $summary['revenue']),               'bi-currency-naira', 'success'],
        ['Platform fee', money((float) $summary['commission']),           'bi-percent',       'warning'],
        ['Your earnings', money((float) $summary['earnings']),            'bi-wallet2',       'info'],
        ['Average order', money((float) $summary['average_order']),        'bi-graph-up',      'secondary'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone]): ?>
        <div class="col-6 col-xl">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">Top products</h3>
                    <a href="/pharmacy/reports/products" class="section-link">Full report</a>
                </div>
                <?php if (empty($products)): ?>
                    <p class="small text-muted mb-0">No sales in this period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Units</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="small text-truncate" style="max-width:180px">
                                            <?= e(str_excerpt((string) $product['product_name'], 40)) ?>
                                        </td>
                                        <td class="text-end small"><?= (int) $product['units'] ?></td>
                                        <td class="text-end small fw-semibold"><?= money_compact((float) $product['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Orders by status</h3>
                <?php if (empty($byStatus)): ?>
                    <p class="small text-muted mb-0">No orders in this period.</p>
                <?php else: ?>
                    <?php foreach ($byStatus as $row): ?>
                        <div class="d-flex justify-content-between align-items-center py-1"
                            style="border-bottom:1px dashed var(--ipl-border)">
                            <span><?= status_badge((string) $row['status']) ?></span>
                            <span class="small">
                                <strong><?= (int) $row['total'] ?></strong>
                                <span class="text-muted ms-2"><?= money_compact((float) $row['revenue']) ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Low stock</h3>
                    <a href="/pharmacy/inventory/low-stock" class="section-link">Manage</a>
                </div>
                <?php if (empty($lowStock)): ?>
                    <p class="small text-muted mb-0">Stock levels look healthy.</p>
                <?php else: ?>
                    <?php foreach ($lowStock as $item): ?>
                        <div class="d-flex justify-content-between small py-1" style="border-bottom:1px dashed var(--ipl-border)">
                            <span class="text-truncate me-2"><?= e(str_excerpt((string) $item['name'], 40)) ?></span>
                            <span class="badge bg-<?= (int) $item['stock_qty'] === 0 ? 'danger' : 'warning' ?>">
                                <?= (int) $item['stock_qty'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ipl-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-calendar-x text-danger me-1"></i>Expiring</h3>
                    <a href="/pharmacy/inventory/expiring" class="section-link">Manage</a>
                </div>
                <?php if (empty($expiring)): ?>
                    <p class="small text-muted mb-0">Nothing is expiring soon.</p>
                <?php else: ?>
                    <?php foreach ($expiring as $item): ?>
                        <div class="d-flex justify-content-between small py-1" style="border-bottom:1px dashed var(--ipl-border)">
                            <span class="text-truncate me-2"><?= e(str_excerpt((string) $item['name'], 40)) ?></span>
                            <span class="text-muted">
                                <?= e(date('M Y', strtotime((string) $item['expiry_date']))) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-3">
    <a href="/pharmacy/reports/sales" class="btn btn-light btn-sm">Sales report</a>
    <a href="/pharmacy/reports/products" class="btn btn-light btn-sm">Product sales</a>
    <a href="/pharmacy/reports/inventory" class="btn btn-light btn-sm">Inventory valuation</a>
</div>