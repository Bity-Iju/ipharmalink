<?php

/**
 * Product sales report — /pharmacy/reports/products
 *
 * @var array $rows
 */
?>
<h2 class="h5 fw-bold mb-3">Product sales</h2>

<?php if (empty($rows)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-box-seam"></i></div>
        <h3 class="h5 fw-bold">No sales recorded yet</h3>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Units sold</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="small fw-semibold">
                                <?php if (!empty($row['product_id'])): ?>
                                    <a href="/pharmacy/products/edit/<?= (int) $row['product_id'] ?>" class="text-reset">
                                        <?= e((string) $row['product_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $row['product_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e((string) ($row['sku'] ?? '—')) ?></td>
                            <td class="text-end small"><?= (int) $row['orders'] ?></td>
                            <td class="text-end"><?= (int) $row['units'] ?></td>
                            <td class="text-end fw-semibold"><?= money((float) $row['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>