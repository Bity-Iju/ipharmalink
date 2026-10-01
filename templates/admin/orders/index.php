<?php

/**
 * Admin orders — /admin/orders and its filtered variants
 *
 * @var \App\Paginator $paginator
 * @var array  $counts
 * @var string $filter
 * @var string $status
 * @var string $search
 */
$tabs = [
    ''           => 'All',
    'pending'    => 'Pending',
    'processing' => 'Processing',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
];
?>
<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($tabs as $key => $label):
        $count = $key === '' ? array_sum(array_map('intval', $counts)) : (int) ($counts[$key] ?? 0); ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3<?= $filter === $key ? ' active' : '' ?>"
                href="<?= $key === '' ? '/admin/orders' : '/admin/orders/' . $key ?>"
                style="<?= $filter === $key ? '' : 'color:var(--ipl-ink)' ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-4">
            <input type="search" name="q" class="form-control" placeholder="Order number or customer…" value="<?= e($search) ?>">
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
            <input type="date" name="from" class="form-control" placeholder="From date">
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No orders matched your filters.',
    'columns'   => [
        ['key' => 'order_number', 'label' => 'Order', 'link' => '/admin/orders/{id}'],
        ['key' => 'customer_name', 'label' => 'Customer', 'truncate' => 22],
        ['key' => 'pharmacy_names', 'label' => 'Pharmacies', 'truncate' => 26, 'class' => 'small text-muted'],
        ['key' => 'created_at', 'label' => 'Date', 'type' => 'date', 'class' => 'small text-muted'],
        ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'align' => 'end'],
        ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'badge'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'actions'   => [
        ['label' => 'View order', 'icon' => 'bi-eye', 'href' => '/admin/orders/{id}', 'variant' => 'btn-light'],
    ],
]); ?>