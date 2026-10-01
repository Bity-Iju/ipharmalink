<?php

/**
 * Rider delivery list — /delivery/orders
 *
 * @var \App\Paginator $paginator
 * @var string $status
 */
$deliveries = $paginator->items();
$statuses = [
    '' => 'All',
    'assigned' => 'Assigned',
    'picked_up' => 'Picked up',
    'in_transit' => 'In transit',
    'delivered' => 'Delivered',
    'failed_delivery' => 'Failed',
    'cancelled' => 'Cancelled',
];
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($statuses as $value => $label): ?>
        <a href="/delivery/orders<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($deliveries === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-inbox"></i></div>
        <h3 class="h5 fw-bold">No deliveries here</h3>
        <p class="mb-0">Drops assigned to you will appear in this list.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Pharmacy</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Delivered</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td class="small text-muted">#<?= (int) $delivery['id'] ?></td>
                            <td class="small fw-semibold">
                                <?= e((string) ($delivery['sub_order_number'] ?? $delivery['order_number'])) ?>
                            </td>
                            <td class="small"><?= e((string) $delivery['pharmacy_name']) ?></td>
                            <td class="small">
                                <?= e((string) $delivery['customer_name']) ?>
                                <div class="text-muted small"><?= e((string) $delivery['customer_phone']) ?></div>
                            </td>
                            <td><?= status_badge((string) $delivery['status']) ?></td>
                            <td class="small text-muted">
                                <?= $delivery['delivered_at'] !== null
                                    ? e(date('j M Y H:i', strtotime((string) $delivery['delivered_at'])))
                                    : '—' ?>
                            </td>
                            <td class="text-end">
                                <a href="/delivery/orders/<?= (int) $delivery['id'] ?>" class="btn btn-sm btn-light">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'deliveries']); ?>
<?php endif; ?>