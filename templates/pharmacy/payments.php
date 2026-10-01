<?php

/**
 * Pharmacy payments — /pharmacy/payments
 *
 * @var \App\Paginator $paginator
 * @var string $tab
 * @var string $search
 * @var array  $wallet
 * @var array  $summary
 */
$tabs = [
    'received'    => 'Received',
    'outstanding' => 'Outstanding',
    'wallet'      => 'Wallet ledger',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Payments</h2>
    <a href="/pharmacy/wallet" class="btn btn-sm btn-light">
        <i class="bi bi-wallet2 me-1"></i> Wallet
    </a>
</div>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['Total received', money($summary['received_total']), 'bi-cash-coin', 'success', null],
        ['Awaiting payment', money($summary['outstanding_total']), 'bi-hourglass-split', 'warning', null],
        ['Net earnings', money($summary['earnings_total']), 'bi-graph-up-arrow', 'primary', null],
        ['Refunded', money($summary['refunded_total']), 'bi-arrow-counterclockwise', 'danger', null],
        ['Available balance', money((float) $wallet['balance']), 'bi-wallet2', 'info', '/pharmacy/wallet'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]):
    ?>
        <div class="col-6 col-md-4 col-lg">
            <?php \App\View::include('components/stat-tile', [
                'label' => $label,
                'value' => $value,
                'icon'  => $icon,
                'tone'  => $tone,
                'link'  => $link,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3<?= $tab === $key ? ' active' : '' ?>"
                href="/pharmacy/payments?tab=<?= e($key) ?>"
                style="<?= $tab === $key ? '' : 'color:var(--ipl-ink)' ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab !== 'wallet'): ?>
    <form method="get" class="ipl-card p-3 mb-3">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="row g-2">
            <div class="col-lg-9">
                <input type="search" name="q" class="form-control" placeholder="Order number, reference or customer…"
                    value="<?= e($search) ?>">
            </div>
            <div class="col-lg-3">
                <button class="btn btn-primary w-100">Search</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php $rows = $paginator->items(); ?>

<?php if ($rows === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-receipt"></i></div>
        <h3 class="h5 fw-bold">Nothing to show</h3>
        <p class="mb-0">
            <?= $tab === 'received'
                ? 'Payments appear here once customers pay for their orders.'
                : ($tab === 'outstanding' ? 'Every order has been paid. Well done.' : 'No wallet activity yet.') ?>
        </p>
    </div>
<?php elseif ($tab === 'wallet'): ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance after</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $tx): ?>
                        <tr>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('j M Y H:i', strtotime((string) $tx['created_at']))) ?>
                            </td>
                            <td><?= status_badge((string) $tx['type']) ?></td>
                            <td class="small"><?= e((string) $tx['description']) ?></td>
                            <td class="text-end fw-semibold <?= (float) $tx['amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= (float) $tx['amount'] < 0 ? '−' : '+' ?><?= money(abs((float) $tx['amount'])) ?>
                            </td>
                            <td class="text-end small"><?= money((float) $tx['balance_after']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'entries']); ?>

<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <?php if ($tab === 'received'): ?>
                            <th>Reference</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>When</th>
                        <?php else: ?>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Placed</th>
                        <?php endif; ?>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($tab === 'received'): ?>
                                <td class="small text-muted"><?= e((string) $row['reference']) ?></td>
                                <td class="small">
                                    <a href="/pharmacy/orders/<?= (int) $row['order_id'] ?>" class="fw-semibold text-reset">
                                        <?= e((string) $row['order_number']) ?>
                                    </a>
                                </td>
                                <td class="small"><?= e((string) $row['customer_name']) ?></td>
                                <td class="small text-muted"><?= e(strtoupper((string) $row['gateway_code'])) ?></td>
                                <td><?= status_badge((string) $row['status']) ?></td>
                                <td class="small text-muted text-nowrap">
                                    <?= e(date('j M Y', strtotime((string) $row['paid_at'] ?: (string) $row['created_at']))) ?>
                                </td>
                            <?php else: ?>
                                <td class="small">
                                    <a href="/pharmacy/orders/<?= (int) $row['order_id'] ?>" class="fw-semibold text-reset">
                                        <?= e((string) $row['sub_order_number']) ?>
                                    </a>
                                </td>
                                <td><?= status_badge((string) $row['status']) ?></td>
                                <td class="small text-muted text-nowrap">
                                    <?= e(date('j M Y', strtotime((string) $row['created_at']))) ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-end fw-semibold">
                                <?= money((float) ($tab === 'received' ? $row['amount'] : $row['total'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'records']); ?>
<?php endif; ?>