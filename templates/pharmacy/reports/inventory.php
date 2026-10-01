<?php

/**
 * Inventory valuation — /pharmacy/reports/inventory
 *
 * @var array $rows
 * @var float $totalValue
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Inventory valuation</h2>
    <span class="chip"><?= number_format($totalValue, 2) ?> total value</span>
</div>

<?php if (empty($rows)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-clipboard-data"></i></div>
        <h3 class="h5 fw-bold">No products to value</h3>
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
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit price</th>
                        <th class="text-end">Stock value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $row['name']) ?></td>
                            <td class="small text-muted"><?= e((string) $row['sku']) ?></td>
                            <td class="small text-muted"><?= e((string) ($row['batch_number'] ?? '—')) ?></td>
                            <td class="text-end"><?= (int) $row['stock_qty'] ?></td>
                            <td class="text-end small">
                                <?= money(
                                    (float) ($row['discount_price'] ?? 0) > 0
                                        ? (float) $row['discount_price']
                                        : (float) $row['price']
                                ) ?>
                            </td>
                            <td class="text-end fw-semibold"><?= money((float) $row['stock_value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f3f8f6">
                        <th colspan="5" class="text-end">Total stock value</th>
                        <th class="text-end"><?= money($totalValue) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>