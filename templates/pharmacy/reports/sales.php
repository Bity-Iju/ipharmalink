<?php

/**
 * Daily sales report — /pharmacy/reports/sales
 *
 * @var array  $rows
 * @var string $from
 * @var string $to
 */
$totalRevenue = array_sum(array_column($rows, 'revenue'));
$totalOrders  = array_sum(array_column($rows, 'orders'));
$totalEarnings = array_sum(array_column($rows, 'earnings'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Sales report</h2>
    <form method="get" class="d-flex gap-2 align-items-center">
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
        <button class="btn btn-sm btn-primary">Apply</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-currency-naira',
            'label' => 'Revenue',
            'value' => money($totalRevenue),
            'tone' => 'success',
        ]); ?>
    </div>
    <div class="col-6 col-lg-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-receipt',
            'label' => 'Orders',
            'value' => number_format($totalOrders),
            'tone' => 'primary',
        ]); ?>
    </div>
    <div class="col-6 col-lg-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-wallet2',
            'label' => 'Earnings',
            'value' => money($totalEarnings),
            'tone' => 'info',
        ]); ?>
    </div>
    <div class="col-6 col-lg-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-graph-up',
            'label' => 'Average order',
            'value' => $totalOrders > 0 ? money($totalRevenue / $totalOrders) : money(0),
            'tone' => 'warning',
        ]); ?>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-graph-up"></i></div>
        <h3 class="h5 fw-bold">No sales in this period</h3>
        <p class="mb-0">Try widening the date range.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Platform fee</th>
                        <th class="text-end">Your earnings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($rows) as $row): ?>
                        <tr>
                            <td class="small"><?= e(date('D, j M Y', strtotime((string) $row['day']))) ?></td>
                            <td class="text-end"><?= (int) $row['orders'] ?></td>
                            <td class="text-end fw-semibold"><?= money((float) $row['revenue']) ?></td>
                            <td class="text-end text-danger small">−<?= money((float) $row['commission']) ?></td>
                            <td class="text-end fw-semibold text-success"><?= money((float) $row['earnings']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f3f8f6">
                        <th class="small">Total</th>
                        <th class="text-end"><?= number_format($totalOrders) ?></th>
                        <th class="text-end"><?= money($totalRevenue) ?></th>
                        <th class="text-end text-danger">−<?= money($totalRevenue - $totalEarnings) ?></th>
                        <th class="text-end text-success"><?= money($totalEarnings) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>