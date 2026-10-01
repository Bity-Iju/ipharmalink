<?php

/**
 * Stock movement ledger — /pharmacy/inventory/history
 *
 * @var \App\Paginator $paginator
 * @var string $type
 * @var int    $productId
 * @var array  $products
 */
$movements = $paginator->items();

$types = [
    '' => 'All movements',
    'purchase' => 'Stock received',
    'sale' => 'Sold',
    'return' => 'Returned',
    'adjustment' => 'Adjusted',
    'expiry_writeoff' => 'Expired write-off',
    'release' => 'Cancelled / released',
];
?>
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Stock history</h2>
    <a href="/pharmacy/inventory/adjust" class="btn btn-primary btn-sm">
        <i class="bi bi-sliders me-1"></i> Adjust stock
    </a>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label" for="type">Movement type</label>
            <select name="type" id="type" class="form-select">
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $type === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="product_id">Product</label>
            <select name="product_id" id="product_id" class="form-select">
                <option value="">All products</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= $productId === (int) $product['id'] ? ' selected' : '' ?>>
                        <?= e((string) $product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if ($movements === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-clock-history"></i></div>
        <h3 class="h5 fw-bold">No movements recorded</h3>
        <p class="mb-0">Every stock change is logged here for audit purposes.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-end">Before</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">After</th>
                        <th>Reason</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement):
                        $change = (int) $movement['change_qty']; ?>
                        <tr>
                            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime((string) $movement['created_at']))) ?></td>
                            <td class="small fw-semibold"><?= e((string) ($movement['product_name'] ?? '—')) ?></td>
                            <td>
                                <span class="badge bg-<?= match ((string) $movement['type']) {
                                                            'purchase' => 'success',
                                                            'sale' => 'info',
                                                            'expiry_writeoff' => 'danger',
                                                            'release' => 'warning',
                                                            default => 'secondary',
                                                        } ?>">
                                    <?= e(ucfirst(str_replace('_', ' ', (string) $movement['type']))) ?>
                                </span>
                            </td>
                            <td class="text-end small text-muted"><?= (int) $movement['previous_qty'] ?></td>
                            <td class="text-end fw-semibold small <?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $change >= 0 ? '+' : '' ?><?= $change ?>
                            </td>
                            <td class="text-end small"><?= (int) $movement['new_qty'] ?></td>
                            <td class="small text-muted text-truncate" style="max-width:220px">
                                <?= e((string) ($movement['reason'] ?? '—')) ?>
                            </td>
                            <td class="small text-muted"><?= e((string) ($movement['user_name'] ?? 'System')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'movements']); ?>
<?php endif; ?>