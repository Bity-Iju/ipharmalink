<?php

/**
 * Pharmacy order list — /pharmacy/orders and its filtered variants
 *
 * @var \App\Paginator $paginator
 * @var array $counts
 * @var string $filter
 * @var string $status
 * @var string $search
 */
$orders = $paginator->items();

$tabs = [
    ''            => ['All',        null],
    'new'         => ['New',        ['paid', 'received']],
    'processing'  => ['Processing', ['processing', 'preparing']],
    'ready'       => ['Ready',      ['ready_for_pickup', 'ready_for_delivery']],
    'delivered'   => ['Delivered',  ['delivered']],
    'cancelled'   => ['Cancelled',  ['cancelled', 'refunded']],
];
?>
<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($tabs as $key => [$label, $states]):
        $url = $key === '' ? '/pharmacy/orders' : '/pharmacy/orders/' . $key;
        $count = $states === null
            ? array_sum(array_map('intval', $counts))
            : array_sum(array_map(static fn(string $s): int => (int) ($counts[$s] ?? 0), $states));
    ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3<?= $filter === $key ? ' active' : '' ?>" href="<?= e($url) ?>"
                style="<?= $filter === $key ? '' : 'color:var(--ipl-ink)' ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-6">
            <input type="search" name="q" class="form-control" placeholder="Order number, customer name or phone…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <select name="status" class="form-select">
                <option value="">Any status</option>
                <?php
                $statuses = [
                    'pending_payment' => 'Pending payment',
                    'paid' => 'Paid',
                    'received' => 'Received',
                    'processing' => 'Processing',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for pickup',
                    'ready_for_delivery' => 'Ready for delivery',
                    'out_for_delivery' => 'Out for delivery',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                    'refunded' => 'Refunded',
                ];
                foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if ($orders === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-receipt"></i></div>
        <h3 class="h5 fw-bold">No orders here</h3>
        <p class="mb-0">Orders placed by customers will appear in this list.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Sub-order</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Fulfilment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a href="/pharmacy/orders/<?= (int) $order['id'] ?>" class="fw-semibold small">
                                    <?= e((string) $order['sub_order_number']) ?>
                                </a>
                                <div class="text-muted small">
                                    <?= e(date('j M H:i', strtotime((string) $order['created_at']))) ?>
                                </div>
                            </td>
                            <td class="small">
                                <?= e((string) $order['customer_name']) ?>
                                <div class="text-muted small"><?= e((string) $order['customer_phone']) ?></div>
                            </td>
                            <td class="small">
                                <?= (int) $order['item_count'] ?>
                                <?php if ((int) $order['rx_pending'] > 0): ?>
                                    <div class="badge bg-warning mt-1">
                                        <i class="bi bi-file-earmark-medical me-1"></i>Rx review
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold small"><?= money((float) $order['total']) ?></td>
                            <td><?= status_badge((string) $order['payment_status']) ?></td>
                            <td><?= status_badge((string) $order['status']) ?></td>
                            <td class="small text-capitalize"><?= e((string) $order['fulfilment_method']) ?></td>
                            <td class="text-end">
                                <a href="/pharmacy/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-primary">
                                    Open
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