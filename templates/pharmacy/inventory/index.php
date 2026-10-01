<?php

/**
 * Inventory overview — /pharmacy/inventory
 *
 * @var \App\Paginator $paginator
 * @var array $summary
 * @var string $search
 */
$products = $paginator->items();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Inventory</h2>
    <div class="d-flex gap-2">
        <a href="/pharmacy/inventory/low-stock" class="btn btn-light btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i> Low stock
            <span class="badge bg-warning ms-1"><?= (int) $summary['low_stock'] ?></span>
        </a>
        <a href="/pharmacy/inventory/expiring" class="btn btn-light btn-sm">
            <i class="bi bi-calendar-x me-1"></i> Expiring
        </a>
        <a href="/pharmacy/inventory/adjust" class="btn btn-primary btn-sm">
            <i class="bi bi-sliders me-1"></i> Adjust stock
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Products',  (int) $summary['products'],    'bi-box-seam',  'primary', '/pharmacy/products'],
        ['Units in stock', number_format((int) $summary['units']), 'bi-stack', 'success', '/pharmacy/inventory'],
        ['Low stock', (int) $summary['low_stock'],    'bi-exclamation-triangle', 'warning', '/pharmacy/inventory/low-stock'],
        ['Out of stock', (int) $summary['out_of_stock'], 'bi-x-circle', 'danger', '/pharmacy/products?status=out_of_stock'],
        ['Expired',   (int) $summary['expired'],      'bi-trash3',   'danger', '/pharmacy/inventory/expiring'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
                'link' => $link,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-6">
            <input type="search" name="q" class="form-control" placeholder="Search by product name or SKU…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <button class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-lg-3">
            <a href="/pharmacy/inventory/history" class="btn btn-light w-100">View movement history</a>
        </div>
    </div>
</form>

<?php if ($products === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-clipboard-data"></i></div>
        <h3 class="h5 fw-bold">No products found</h3>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Batch</th>
                        <th class="text-end">In stock</th>
                        <th class="text-end">Reorder at</th>
                        <th>Expiry</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product):
                        $stock = (int) $product['stock_qty'];
                        $low   = $stock <= (int) $product['min_stock_level'];
                        $expired = $product['expiry_date'] !== null && strtotime((string) $product['expiry_date']) < strtotime('today');
                        $days   = $product['expiry_date'] !== null
                            ? (int) floor((strtotime((string) $product['expiry_date']) - time()) / 86400)
                            : null; ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= e(upload_url($product['image'])) ?>" alt="" width="36" height="36"
                                        class="rounded" style="object-fit:contain" loading="lazy">
                                    <a href="/pharmacy/products/edit/<?= (int) $product['id'] ?>" class="small fw-semibold text-reset text-truncate">
                                        <?= e((string) $product['name']) ?>
                                    </a>
                                </div>
                            </td>
                            <td class="small text-muted"><?= e((string) $product['sku']) ?></td>
                            <td class="small text-muted"><?= e((string) ($product['batch_number'] ?? '—')) ?></td>
                            <td class="text-end">
                                <span class="badge bg-<?= $stock === 0 ? 'danger' : ($low ? 'warning' : 'success') ?>">
                                    <?= $stock ?>
                                </span>
                            </td>
                            <td class="text-end small text-muted"><?= (int) $product['min_stock_level'] ?></td>
                            <td class="small">
                                <?php if ($expired): ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php elseif ($days !== null && $days <= 90): ?>
                                    <span class="badge bg-warning"><?= $days ?> days</span>
                                <?php elseif ($product['expiry_date'] !== null): ?>
                                    <span class="text-muted"><?= e(date('M Y', strtotime((string) $product['expiry_date']))) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/pharmacy/products/edit/<?= (int) $product['id'] ?>" class="btn btn-sm btn-light">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'products']); ?>
<?php endif; ?>