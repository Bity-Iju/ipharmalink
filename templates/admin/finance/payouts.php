<?php

/**
 * Admin payouts — /admin/payouts
 *
 * @var \App\Paginator $paginator
 * @var string $status
 */
$statuses = [
    '' => 'All',
    'requested' => 'Requested',
    'approved' => 'Approved',
    'processing' => 'Processing',
    'paid' => 'Paid',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
];
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($statuses as $value => $label): ?>
        <a href="/admin/payouts<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($paginator->items() === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-cash-stack"></i></div>
        <h3 class="h5 fw-bold">No payout requests</h3>
        <p class="mb-0">Requests appear here when a pharmacy asks to withdraw its balance.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Pharmacy</th>
                        <th>Requested</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items() as $payout): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $payout['reference']) ?></td>
                            <td class="small">
                                <a href="/admin/pharmacies/<?= (int) $payout['pharmacy_id'] ?>">
                                    <?= e((string) $payout['pharmacy_name']) ?>
                                </a>
                                <div class="text-muted small">
                                    <?= e((string) ($payout['bank_name'] ?? '')) ?>
                                    <?= e((string) ($payout['account_number'] ?? '')) ?>
                                </div>
                            </td>
                            <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $payout['created_at']))) ?></td>
                            <td class="text-end fw-semibold"><?= money((float) $payout['amount']) ?></td>
                            <td>
                                <?= status_badge((string) $payout['status']) ?>
                                <?php if (!empty($payout['notes'])): ?>
                                    <div class="text-muted small"><?= e((string) $payout['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (in_array((string) $payout['status'], ['requested', 'approved', 'processing'], true)): ?>
                                    <div class="d-inline-flex gap-1">
                                        <form method="post" action="/admin/payouts/<?= (int) $payout['id'] ?>/status" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="approved">
                                            <button class="btn btn-sm btn-success" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/payouts/<?= (int) $payout['id'] ?>/status" class="m-0"
                                            data-confirm="Mark this payout as paid?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="paid">
                                            <button class="btn btn-sm btn-primary" title="Mark paid">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/payouts/<?= (int) $payout['id'] ?>/status" class="m-0"
                                            data-confirm="Reject this payout request?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="notes" value="Rejected by finance">
                                            <button class="btn btn-sm btn-outline-danger" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'payouts']); ?>
<?php endif; ?>