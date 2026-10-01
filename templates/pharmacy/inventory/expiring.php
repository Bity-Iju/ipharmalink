<?php

/**
 * Expiry watchlist — /pharmacy/inventory/expiring
 *
 * @var array $expired
 * @var array $soon
 * @var int   $days
 * @var int   $expiredUnits
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Expiry watchlist</h2>
    <form method="get" class="d-flex gap-2 align-items-center">
        <label class="small text-muted" for="days">Within</label>
        <select name="days" id="days" class="form-select form-select-sm" data-auto-submit>
            <?php foreach ([30, 60, 90, 180, 365] as $option): ?>
                <option value="<?= $option ?>" <?= $days === $option ? ' selected' : '' ?>><?= $option ?> days</option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!empty($expired)): ?>
    <div class="ipl-card mb-4" style="border-color:#dc2626">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="h6 fw-bold mb-1 text-danger">
                        <i class="bi bi-exclamation-octagon me-1"></i>Expired stock
                    </h3>
                    <p class="small text-muted mb-0">
                        <?= count($expired) ?> product(s), <?= (int) $expiredUnits ?> unit(s) past expiry.
                        These must not be dispensed.
                    </p>
                </div>
                <form method="post" action="/pharmacy/inventory/writeoff-expired" class="m-0"
                    data-confirm="Write off all expired stock? This cannot be undone.">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm">Write off expired stock</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table ipl-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th class="text-end">Qty</th>
                            <th>Expired</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expired as $item): ?>
                            <tr>
                                <td class="small fw-semibold"><?= e((string) $item['name']) ?></td>
                                <td class="small text-muted"><?= e((string) ($item['batch_number'] ?? '—')) ?></td>
                                <td class="text-end"><?= (int) $item['stock_qty'] ?></td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?= e(date('j M Y', strtotime((string) $item['expiry_date']))) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($soon)): ?>
    <?php if (empty($expired)): ?>
        <div class="ipl-card empty-state">
            <div class="icon"><i class="bi bi-calendar-check"></i></div>
            <h3 class="h5 fw-bold">Nothing is expiring soon</h3>
            <p class="mb-0">No product expires within the next <?= (int) $days ?> days.</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="ipl-card">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3"><i class="bi bi-calendar-x text-warning me-1"></i>Expiring soon</h3>
            <div class="table-responsive">
                <table class="table ipl-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th class="text-end">Qty</th>
                            <th>Expires</th>
                            <th>Days</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($soon as $item): ?>
                            <tr>
                                <td class="small fw-semibold"><?= e((string) $item['name']) ?></td>
                                <td class="small text-muted"><?= e((string) ($item['batch_number'] ?? '—')) ?></td>
                                <td class="text-end"><?= (int) $item['stock_qty'] ?></td>
                                <td class="small"><?= e(date('j M Y', strtotime((string) $item['expiry_date']))) ?></td>
                                <td>
                                    <?php $left = (int) $item['days_left']; ?>
                                    <span class="badge bg-<?= $left <= 30 ? 'danger' : ($left <= 60 ? 'warning' : 'info') ?>">
                                        <?= $left ?> days
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/pharmacy/products/edit/<?= (int) $item['id'] ?>" class="btn btn-sm btn-light">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>