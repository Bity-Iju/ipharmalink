<?php

/**
 * Admin reports hub — /admin/reports
 *
 * @var array $tiles          headline KPIs
 * @var array $series         daily orders + revenue
 * @var array $topPharmacies  pharmacy league table
 * @var array $reports        slug => title
 * @var string $from
 * @var string $to
 */
$maxRevenue = 0.0;
foreach ($series as $point) {
    $maxRevenue = max($maxRevenue, (float) $point['revenue']);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Platform reports</h2>
        <p class="text-muted small mb-0">
            <?= e(date('j M Y', (int) strtotime($from))) ?> – <?= e(date('j M Y', (int) strtotime($to))) ?>
        </p>
    </div>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3">
            <label class="form-label" for="from">From</label>
            <input type="date" name="from" id="from" class="form-control" value="<?= e($from) ?>">
        </div>
        <div class="col-lg-3">
            <label class="form-label" for="to">To</label>
            <input type="date" name="to" id="to" class="form-control" value="<?= e($to) ?>">
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Apply</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <?php
    $headline = [
        ['GMV', money_compact($tiles['gmv']), 'bi-cash-stack', 'primary', 'sales'],
        ['Orders', number_format($tiles['orders']), 'bi-receipt', 'info', 'orders'],
        ['Commission', money_compact($tiles['commission']), 'bi-percent', 'success', 'commissions'],
        ['Refunded', money_compact($tiles['refunded']), 'bi-arrow-counterclockwise', 'warning', 'refunds'],
        ['Delivered', number_format($tiles['delivered']), 'bi-truck', 'primary', 'deliveries'],
        ['Failed payments', number_format($tiles['failed_payments']), 'bi-x-circle', 'danger', 'payments'],
        ['Approved pharmacies', number_format($tiles['pharmacies']), 'bi-shop', 'info', 'pharmacies'],
        ['Customers', number_format($tiles['customers']), 'bi-people', 'primary', 'customers'],
    ];
    foreach ($headline as [$label, $value, $icon, $tone, $slug]):
    ?>
        <div class="col-6 col-md-3">
            <?php \App\View::include('components/stat-tile', [
                'label' => $label,
                'value' => $value,
                'icon'  => $icon,
                'tone'  => $tone,
                'link'  => '/admin/reports/' . $slug,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Daily merchandise value</h3>

            <?php if ($series === [] || $maxRevenue <= 0): ?>
                <p class="text-muted small mb-0">No sales recorded in this window.</p>
            <?php else: ?>
                <div style="height:200px;display:flex;align-items:flex-end;gap:2px">
                    <?php foreach (array_slice($series, -60) as $point):
                        $height = max(2, (int) round(((float) $point['revenue'] / $maxRevenue) * 190)); ?>
                        <div class="flex-fill d-flex flex-column justify-content-end align-items-center"
                            title="<?= e(date('j M', (int) strtotime((string) $point['day']))) ?>:
<?= e(money((float) $point['revenue'])) ?> · <?= (int) $point['orders'] ?> orders">
                            <div style="width:100%;height:<?= $height ?>px;background:var(--ipl-primary);border-radius:2px 2px 0 0;opacity:.85"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Top pharmacies by earnings</h3>

            <?php if ($topPharmacies === []): ?>
                <p class="text-muted small mb-0">No pharmacy activity yet.</p>
            <?php else: ?>
                <div class="d-grid gap-2">
                    <?php foreach ($topPharmacies as $pharmacy): ?>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="small fw-semibold text-truncate"><?= e((string) $pharmacy['name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem">
                                    <?= e((string) $pharmacy['city']) ?> ·
                                    <?= (int) $pharmacy['orders'] ?> orders
                                </div>
                            </div>
                            <div class="small fw-semibold text-nowrap">
                                <?= e(money_compact((float) $pharmacy['earnings'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<h3 class="h6 fw-bold mb-2">All reports</h3>
<div class="row g-3">
    <?php foreach ($reports as $slug => $reportTitle): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="/admin/reports/<?= e($slug) ?>" class="ipl-card p-3 d-block text-reset h-100">
                <div class="fw-semibold small mb-1"><?= e($reportTitle) ?></div>
                <div class="text-muted" style="font-size:.72rem">View &amp; export</div>
            </a>
        </div>
    <?php endforeach; ?>
</div>