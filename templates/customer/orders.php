<?php

/**
 * Order history — /account/orders
 *
 * @var \App\Paginator $paginator
 * @var array $counts
 * @var string $status
 * @var string $search
 */
$orders = $paginator->items();
$statuses = [
    '' => 'All',
    'pending_payment' => 'Pending payment',
    'paid' => 'Paid',
    'received' => 'Received',
    'processing' => 'Processing',
    'preparing' => 'Preparing',
    'ready_for_delivery' => 'Ready',
    'out_for_delivery' => 'Out for delivery',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];
?>
<div class="ipl-card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <form method="get" class="d-flex gap-2 flex-grow-1" style="max-width:340px">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search order number…"
                value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-primary">Search</button>
        </form>
    </div>

        <div class="d-flex flex-wrap gap-1 mt-3">
        <?php $totalCount = array_sum(array_map('intval', $counts)); foreach ($statuses as $value => $label):
            $count = $value === '' ? $totalCount : ($counts[$value] ?? 0); ?>
            <a href="/account/orders<?= $value !== '' ? '?status=' . e($value) : '' ?>"
                class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>">
                <?= e($label) ?> (<?= (int) $count ?>)
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($orders === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-receipt"></i></div>
        <h2 class="h5 fw-bold">No orders found</h2>
        <p class="mb-3"><?= $search !== '' || $status !== '' ? 'Try clearing your filters.' : 'When you place an order it will appear here.' ?></p>
        <a href="/products" class="btn btn-primary btn-sm">Start shopping</a>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Pharmacy</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a href="/account/orders/<?= (int) $order['id'] ?>" class="fw-semibold">
                                    <?= e((string) $order['order_number']) ?>
                                </a>
                                <div class="text-muted small text-capitalize"><?= e((string) $order['fulfilment_method']) ?></div>
                            </td>
                            <td class="small">
                                <?= e(str_excerpt((string) ($order['pharmacy_names'] ?? '—'), 36)) ?>
                                <?php if ((int) ($order['slice_count'] ?? 1) > 1): ?>
                                    <div class="text-muted small">+<?= (int) $order['slice_count'] - 1 ?> more</div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $order['created_at']))) ?></td>
                            <td class="small"><?= (int) $order['item_count'] ?></td>
                            <td class="fw-semibold"><?= money((float) $order['total']) ?></td>
                            <td><?= status_badge((string) $order['status']) ?></td>
                            <td><?= status_badge((string) $order['payment_status']) ?></td>
                            <td class="text-end">
                                <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-light">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'orders']); ?>
<?php endif; ?>