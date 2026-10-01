<?php

/**
 * Customer payment history — /account/payments
 *
 * @var \App\Paginator $paginator
 * @var array $summary
 * @var string $status
 */
$statuses = [
    ''           => 'All',
    'successful' => 'Successful',
    'pending'    => 'Pending',
    'processing' => 'Processing',
    'failed'     => 'Failed',
    'refunded'   => 'Refunded',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Payments</h2>
    <a href="/account/orders" class="btn btn-sm btn-light">
        <i class="bi bi-receipt me-1"></i> My orders
    </a>
</div>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Total paid', money($summary['paid']), 'bi-check-circle', 'success'],
        ['Awaiting payment', money($summary['pending']), 'bi-hourglass-split', 'warning'],
        ['Refunded', money($summary['refunded']), 'bi-arrow-counterclockwise', 'info'],
        ['Transactions', number_format($summary['total']), 'bi-receipt', 'primary'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone]):
    ?>
        <div class="col-6 col-md-3">
            <?php \App\View::include('components/stat-tile', [
                'label' => $label,
                'value' => $value,
                'icon'  => $icon,
                'tone'  => $tone,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($statuses as $key => $label): ?>
        <a href="/account/payments<?= $key !== '' ? '?status=' . e($key) : '' ?>"
            class="chip<?= $status === $key ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($paginator->isEmpty()): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-credit-card"></i></div>
        <h3 class="h5 fw-bold">No payments yet</h3>
        <p class="mb-0">Your payment history will appear here after your first order.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Order</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items() as $payment): ?>
                        <tr>
                            <td class="small text-muted"><?= e((string) $payment['reference']) ?></td>
                            <td class="small">
                                <a href="/account/orders/<?= (int) $payment['order_id'] ?>" class="fw-semibold text-reset">
                                    <?= e((string) $payment['order_number']) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= e(strtoupper((string) $payment['gateway_code'])) ?></td>
                            <td><?= status_badge((string) $payment['status']) ?></td>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('j M Y', strtotime((string) ($payment['paid_at'] ?: $payment['created_at'])))) ?>
                            </td>
                            <td class="text-end fw-semibold"><?= money((float) $payment['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'payments']); ?>
<?php endif; ?>