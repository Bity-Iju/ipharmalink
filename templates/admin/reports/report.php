<?php

/**
 * Admin report — shared detail view for every report slug.
 *
 * @var \App\Paginator $paginator
 * @var array $columns   column key => label
 * @var array $tiles     numeric column => summed value for this page
 * @var string $slug
 * @var string $from
 * @var string $to
 * @var array  $reports  slug => title
 */
$rows = $paginator->items();

/** Format a column value for display. */
$cell = static function (mixed $value, string $key): string {
    if ($value === null || $value === '') {
        return '—';
    }

    // Money-ish column keys get the currency treatment.
    $moneyKeys = [
        'gmv',
        'total',
        'amount',
        'revenue',
        'earnings',
        'commission_amount',
        'base_amount',
        'pharmacy_earnings',
        'gross_merchandise_value',
        'platform_commission',
        'stock_value',
        'delivery_fee',
        'value',
        'price',
    ];
    if (in_array($key, $moneyKeys, true)) {
        return e(money((float) $value));
    }

    if (in_array($key, ['status', 'payment_status', 'health'], true)) {
        return status_badge((string) $value);
    }

    if (preg_match('/(_at|_date|registered|day|created|delivered_on|expiry_date)$/', $key) && strtotime((string) $value) !== false) {
        return e(date('j M Y', (int) strtotime((string) $value)));
    }

    if (is_numeric($value) && (float) $value === floor((float) $value) && abs((float) $value) < 100000) {
        return e(number_format((float) $value));
    }

    return e((string) $value);
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1"><?= e($title) ?></h2>
        <p class="text-muted small mb-0"><?= e(date('j M Y', (int) strtotime($from))) ?>
            – <?= e(date('j M Y', (int) strtotime($to))) ?></p>
    </div>
    <a href="/admin/reports/export/<?= e($slug) ?>?from=<?= e($from) ?>&to=<?= e($to) ?>"
        class="btn btn-sm btn-success">
        <i class="bi bi-filetype-csv me-1"></i> Export CSV
    </a>
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

<?php if ($tiles !== []): ?>
    <div class="row g-3 mb-3">
        <?php foreach ($tiles as $key => $value): ?>
            <?php $label = $columns[$key] ?? ucfirst(str_replace('_', ' ', (string) $key)); ?>
            <div class="col-6 col-md-3">
                <div class="ipl-card p-3">
                    <div class="small text-muted mb-1"><?= e($label) ?></div>
                    <div class="fw-bold">
                        <?= in_array($key, ['gmv', 'total', 'amount', 'revenue', 'earnings', 'commission_amount', 'base_amount', 'pharmacy_earnings', 'gross_merchandise_value', 'platform_commission', 'stock_value', 'delivery_fee', 'price'], true)
                            ? e(money((float) $value))
                            : e(number_format((float) $value, 0)) ?>
                    </div>
                    <div class="text-muted small">this page</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-bar-chart"></i></div>
        <h3 class="h5 fw-bold">Nothing in this window</h3>
        <p class="mb-0">Widen the date range to see results.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <?php foreach ($columns as $label): ?>
                            <th class="small"><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_keys($columns) as $key): ?>
                                <td class="small"><?= $cell($row[$key] ?? null, $key) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'rows']); ?>
<?php endif; ?>