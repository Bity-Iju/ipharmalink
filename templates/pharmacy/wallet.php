<?php

/**
 * Pharmacy wallet — /pharmacy/wallet
 *
 * @var array $balance
 * @var array $transactions
 */
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-wallet2',
            'label' => 'Available balance',
            'value' => money((float) $balance['balance']),
            'tone' => 'success',
            'hint' => 'Ready to withdraw',
        ]); ?>
    </div>
    <div class="col-md-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-hourglass-split',
            'label' => 'Pending',
            'value' => money((float) $balance['pending_balance']),
            'tone' => 'warning',
            'hint' => 'Released on delivery',
        ]); ?>
    </div>
    <div class="col-md-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-graph-up-arrow',
            'label' => 'Lifetime earned',
            'value' => money_compact((float) $balance['total_earned']),
            'tone' => 'primary',
        ]); ?>
    </div>
    <div class="col-md-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-cash-stack',
            'label' => 'Total paid out',
            'value' => money_compact((float) $balance['total_paid']),
            'tone' => 'info',
        ]); ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Transaction history</h2>
    <a href="/pharmacy/payouts" class="btn btn-sm btn-primary">Request a payout</a>
</div>

<?php if (empty($transactions)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-wallet2"></i></div>
        <h3 class="h5 fw-bold">No transactions yet</h3>
        <p class="mb-0">Earnings appear here once an order is delivered.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime((string) $tx['created_at']))) ?></td>
                            <td>
                                <span class="badge bg-<?= match ((string) $tx['type']) {
                                                            'credit' => 'success',
                                                            'debit'  => 'danger',
                                                            'payout' => 'info',
                                                            default  => 'secondary',
                                                        } ?>">
                                    <?= e(ucfirst((string) $tx['type'])) ?>
                                </span>
                            </td>
                            <td class="small"><?= e((string) ($tx['description'] ?? '—')) ?></td>
                            <td class="text-end fw-semibold <?= (float) $tx['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= (float) $tx['amount'] >= 0 ? '+' : '' ?><?= money((float) $tx['amount']) ?>
                            </td>
                            <td class="text-end small text-muted"><?= money((float) $tx['balance_after']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>