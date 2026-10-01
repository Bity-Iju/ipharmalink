<?php

/**
 * Rider history — /delivery/history
 *
 * @var array $rows
 * @var array $summary
 * @var string $from
 * @var string $to
 */
?>
<form method="get" class="d-flex gap-2 align-items-center mb-3">
    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
    <button class="btn btn-sm btn-primary">Apply</button>
</form>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Deliveries', number_format((float) $summary['total']),  'bi-check2-circle', 'success'],
        ['Earned',     money((float) $summary['earned']),        'bi-cash-coin',    'primary'],
        ['Failed',     number_format((float) $summary['failed']), 'bi-x-circle',     'danger'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone]): ?>
        <div class="col-md-4">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($rows)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-clock-history"></i></div>
        <h3 class="h5 fw-bold">No completed deliveries in this period</h3>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Delivered</th>
                        <th>Order</th>
                        <th>Pharmacy</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-end">Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime((string) $row['delivered_at']))) ?></td>
                            <td class="small fw-semibold">
                                <?= e((string) ($row['sub_order_number'] ?? $row['order_number'])) ?>
                            </td>
                            <td class="small"><?= e((string) $row['pharmacy_name']) ?></td>
                            <td class="small"><?= e((string) $row['customer_name']) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="text-end small"><?= money((float) $row['delivery_fee']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>