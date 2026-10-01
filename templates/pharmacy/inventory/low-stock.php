<?php

/**
 * Low stock alerts — /pharmacy/inventory/low-stock
 *
 * @var array $products
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Low stock alerts</h2>
    <a href="/pharmacy/inventory/adjust" class="btn btn-primary btn-sm">
        <i class="bi bi-sliders me-1"></i> Receive stock
    </a>
</div>

<?php if ($products === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-check2-all"></i></div>
        <h3 class="h5 fw-bold">Stock levels look healthy</h3>
        <p class="mb-0">No product has fallen to its reorder point.</p>
    </div>
<?php else: ?>
    <div class="alert alert-warning small">
        <strong><?= count($products) ?> product(s)</strong> are at or below their reorder level.
        They will be hidden from customers once stock reaches zero.
    </div>

    <div class="row g-3">
        <?php foreach ($products as $product):
            $stock = (int) $product['stock_qty']; ?>
            <div class="col-sm-6 col-lg-4 col-xxl-3">
                <div class="ipl-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="<?= e(upload_url($product['image'])) ?>" alt="" width="44" height="44"
                            class="rounded" style="object-fit:contain" loading="lazy">
                        <div class="min-w-0">
                            <a href="/pharmacy/products/edit/<?= (int) $product['id'] ?>" class="small fw-semibold text-reset d-block text-truncate">
                                <?= e((string) $product['name']) ?>
                            </a>
                            <span class="text-muted small">SKU: <?= e((string) $product['sku']) ?></span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">In stock</span>
                            <span class="badge bg-<?= $stock === 0 ? 'danger' : 'warning' ?>"><?= $stock ?></span>
                        </div>
                        <div class="progress" style="height:5px">
                            <?php $level = (int) $product['min_stock_level'] > 0
                                ? min(100, (int) round($stock / (int) $product['min_stock_level'] * 100))
                                : 100; ?>
                            <div class="progress-bar bg-<?= $stock === 0 ? 'danger' : 'warning' ?>" style="width:<?= $level ?>%"></div>
                        </div>
                        <div class="text-muted small mt-1">Reorder at <?= (int) $product['min_stock_level'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>