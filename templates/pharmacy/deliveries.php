<?php

/**
 * Pharmacy deliveries — /pharmacy/deliveries
 *
 * @var \App\Paginator $paginator
 * @var array $counts
 * @var array $riders
 */
$deliveries = $paginator->items();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Deliveries</h2>
    <span class="text-muted small"><?= number_format($paginator->total()) ?> total</span>
</div>

<?php if (empty($deliveries)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-truck"></i></div>
        <h3 class="h5 fw-bold">No deliveries yet</h3>
        <p class="mb-0">Delivery orders appear here once a customer checks out for delivery.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Rider</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td class="small text-muted">#<?= (int) $delivery['id'] ?></td>
                            <td class="small">
                                <?php if (!empty($delivery['sub_order_number'])): ?>
                                    <a href="/pharmacy/orders/<?= (int) $delivery['pharmacy_order_id'] ?>" class="fw-semibold">
                                        <?= e((string) $delivery['sub_order_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $delivery['order_number']) ?>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <?= e((string) $delivery['pharmacy_name']) ?>
                                </div>
                            </td>
                            <td class="small">
                                <?= e((string) $delivery['customer_name']) ?>
                                <div class="text-muted small"><?= e((string) $delivery['customer_phone']) ?></div>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width:200px">
                                <?= e(str_excerpt((string) ($delivery['dropoff_address'] ?? '—'), 50)) ?>
                            </td>
                            <td><?= status_badge((string) $delivery['status']) ?></td>
                            <td class="small">
                                <?php if (!empty($delivery['rider_name'])): ?>
                                    <?= e((string) $delivery['rider_name']) ?>
                                    <div class="text-muted small"><?= e((string) $delivery['rider_phone']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (in_array((string) $delivery['status'], ['pending_assignment', 'assigned'], true)): ?>
                                    <form method="post" action="/pharmacy/deliveries/<?= (int) $delivery['id'] ?>/assign"
                                        class="d-flex gap-1 justify-content-end">
                                        <?= csrf_field() ?>
                                        <select name="personnel_id" class="form-select form-select-sm" style="width:auto">
                                            <option value="">Choose rider…</option>
                                            <?php foreach ($riders as $rider): ?>
                                                <option value="<?= (int) $rider['id'] ?>"
                                                    <?= (int) ($delivery['personnel_id'] ?? 0) === (int) $rider['id'] ? ' selected' : '' ?>>
                                                    <?= e((string) $rider['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-primary">Assign</button>
                                    </form>
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

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'deliveries']); ?>
<?php endif; ?>