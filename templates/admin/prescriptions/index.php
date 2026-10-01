<?php

/**
 * Admin prescription oversight — /admin/prescriptions
 *
 * @var \App\Paginator $paginator
 * @var array $counts
 * @var string $status
 * @var string $search
 */
$tabs = ['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Prescriptions
        <span class="text-muted fw-normal">(<?= number_format($paginator->total()) ?>)</span>
    </h2>
</div>

<div class="alert alert-info small">
    <i class="bi bi-shield-lock me-1"></i>
    Prescription records are compliance artefacts. They are never deleted, only approved,
    rejected or expired, and every decision is written to the audit log.
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($tabs as $key => $label):
        $count = $key === '' ? $counts['all'] : (int) ($counts[$key] ?? 0); ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3<?= $status === $key ? ' active' : '' ?>"
                href="/admin/prescriptions<?= $key !== '' ? '?status=' . e($key) : '' ?>"
                style="<?= $status === $key ? '' : 'color:var(--ipl-ink)' ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-9">
            <input type="search" name="q" class="form-control" placeholder="Patient name, customer or pharmacy…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <button class="btn btn-primary w-100">Search</button>
        </div>
    </div>
</form>

<?php if ($paginator->isEmpty()): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-file-earmark-medical"></i></div>
        <h3 class="h5 fw-bold">No prescriptions</h3>
        <p class="mb-0">Prescriptions submitted by customers appear here for review.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Prescription</th>
                        <th>Customer</th>
                        <th>Pharmacy</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Reviewed by</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items() as $prescription): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $prescription['original_name']) ?></td>
                            <td class="small"><?= e((string) $prescription['customer_name']) ?></td>
                            <td class="small text-muted"><?= e((string) $prescription['pharmacy_name']) ?></td>
                            <td class="small text-muted">
                                <?= $prescription['order_number'] !== null
                                    ? e((string) $prescription['order_number'])
                                    : '—' ?>
                            </td>
                            <td><?= status_badge((string) $prescription['status']) ?></td>
                            <td class="small text-muted">
                                <?= e((string) ($prescription['reviewer_name'] ?? '—')) ?>
                            </td>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('j M Y', strtotime((string) $prescription['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'prescriptions']); ?>
<?php endif; ?>