<?php

/**
 * Admin delivery oversight — /admin/deliveries
 *
 * @var \App\Paginator $paginator
 * @var array $counts
 * @var string $status
 * @var string $search
 */
$tabs = [
    ''          => 'All',
    'pending'   => 'Pending',
    'assigned'  => 'Assigned',
    'in_transit' => 'In transit',
    'delivered' => 'Delivered',
    'failed'    => 'Failed',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Deliveries <span class="text-muted fw-normal">(<?= number_format($paginator->total()) ?>)</span></h2>
    <a href="/admin/delivery-personnel" class="btn btn-sm btn-light">
        <i class="bi bi-people me-1"></i> Riders
    </a>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($tabs as $key => $label):
        $count = $key === '' ? $counts['all'] : (int) ($counts[$key] ?? 0); ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3<?= $status === $key ? ' active' : '' ?>"
                href="/admin/deliveries<?= $key !== '' ? '?status=' . e($key) : '' ?>"
                style="<?= $status === $key ? '' : 'color:var(--ipl-ink)' ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="ipl-card p-3 text-center">
            <div class="small text-muted">Active now</div>
            <div class="h4 fw-bold mb-0"><?= number_format($counts['active']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ipl-card p-3 text-center">
            <div class="small text-muted">Delivered</div>
            <div class="h4 fw-bold mb-0 text-success"><?= number_format($counts['delivered']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ipl-card p-3 text-center">
            <div class="small text-muted">Failed</div>
            <div class="h4 fw-bold mb-0 text-danger"><?= number_format($counts['failed']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ipl-card p-3 text-center">
            <div class="small text-muted">Completion rate</div>
            <?php $settled = $counts['delivered'] + $counts['failed']; ?>
            <div class="h4 fw-bold mb-0">
                <?= $settled > 0 ? number_format($counts['delivered'] / $settled * 100, 1) : '0' ?>%
            </div>
        </div>
    </div>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-5">
            <input type="search" name="q" class="form-control" placeholder="Tracking number, pharmacy or rider…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <input type="date" name="from" class="form-control" placeholder="From">
        </div>
        <div class="col-lg-2">
            <input type="date" name="to" class="form-control" placeholder="To">
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if ($paginator->isEmpty()): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-truck"></i></div>
        <h3 class="h5 fw-bold">No deliveries found</h3>
        <p class="mb-0">Try widening the filters.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Pharmacy</th>
                        <th>Rider</th>
                        <th>Status</th>
                        <th class="text-end">Fee</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items() as $delivery): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $delivery['tracking_number']) ?></td>
                            <td class="small">
                                <a href="/admin/pharmacies/<?= (int) $delivery['pharmacy_id'] ?>" class="text-reset fw-semibold">
                                    <?= e((string) $delivery['pharmacy_name']) ?>
                                </a>
                            </td>
                            <td class="small text-muted">
                                <?= e((string) ($delivery['rider_name'] ?? 'Unassigned')) ?>
                            </td>
                            <td><?= status_badge((string) $delivery['status']) ?></td>
                            <td class="text-end small"><?= money((float) $delivery['delivery_fee']) ?></td>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('j M Y', strtotime((string) $delivery['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'deliveries']); ?>
<?php endif; ?>