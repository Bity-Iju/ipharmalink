<?php
/**
 * Payout requests — /pharmacy/payouts
 *
 * @var array  $balance
 * @var array  $payouts
 * @var float  $minimum
 * @var bool   $canRequest
 * @var array  $bank
 */
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-wallet2', 'label' => 'Available to withdraw', 'value' => money((float) $balance['balance']),
            'tone' => 'success',
        ]); ?>
    </div>
    <div class="col-md-4">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-hourglass-split', 'label' => 'Pending release', 'value' => money((float) $balance['pending_balance']),
            'tone' => 'warning',
        ]); ?>
    </div>
    <div class="col-md-4">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-info-circle', 'label' => 'Minimum payout', 'value' => money($minimum),
            'tone' => 'primary',
        ]); ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h5 fw-bold mb-3">Payout history</h2>

        <?php if (empty($payouts)): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-cash-stack"></i></div>
                <h3 class="h5 fw-bold">No payout requests yet</h3>
                <p class="mb-0">Once your balance reaches <?= money($minimum) ?>, you can request a payout.</p>
            </div>
        <?php else: ?>
            <div class="ipl-card">
                <div class="table-responsive">
                    <table class="table ipl-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Requested</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payouts as $payout): ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e((string) $payout['reference']) ?></td>
                                    <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $payout['created_at']))) ?></td>
                                    <td class="text-end fw-semibold"><?= money((float) $payout['amount']) ?></td>
                                    <td>
                                        <?= status_badge((string) $payout['status']) ?>
                                        <?php if (!empty($payout['notes'])): ?>
                                            <div class="text-muted small"><?= e((string) $payout['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Request a payout</h2>

            <?php if (!$canRequest): ?>
                <div class="alert alert-warning small">
                    You need at least <strong><?= money($minimum) ?></strong> in your balance to request
                    a payout. You currently have <?= money((float) $balance['balance']) ?>.
                </div>
            <?php else: ?>
                <form method="post" action="/pharmacy/payouts"
                      data-confirm="Request a payout of your full available balance?">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <div class="text-muted small">Amount that will be paid out</div>
                        <div class="fs-4 fw-bold text-success"><?= money((float) $balance['balance']) ?></div>
                    </div>

                    <hr>

                    <div class="small mb-3">
                        <div class="text-muted">Paid to</div>
                        <div class="fw-semibold"><?= e((string) ($bank['bank_account_name'] ?? 'Not set')) ?></div>
                        <div class="text-muted"><?= e((string) ($bank['bank_name'] ?? '')) ?></div>
                        <div class="text-muted"><?= e((string) ($bank['bank_account_number'] ?? '')) ?></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> Request payout
                    </button>
                </form>
            <?php endif; ?>

            <hr>
            <p class="small text-muted mb-0">
                Payouts are usually processed within two business days after approval.
                Update your bank details from <a href="/pharmacy/profile">your profile</a>.
            </p>
        </div>
    </div>
</div>
