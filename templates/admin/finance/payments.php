<?php

/**
 * Admin payments — /admin/payments
 *
 * @var \App\Paginator $paginator
 * @var array $stats
 * @var string $status
 */
?>
<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Payments',   (int) ($stats['total'] ?? 0),        'bi-credit-card',   'primary', '/admin/payments'],
        ['Successful', (int) ($stats['successful'] ?? 0),  'bi-check2-circle', 'success', '/admin/payments?status=successful'],
        ['Failed',     (int) ($stats['failed'] ?? 0),      'bi-x-circle',      'danger',  '/admin/payments?status=failed'],
        ['Refunded',   (int) ($stats['refunded'] ?? 0),    'bi-arrow-counterclockwise', 'warning', '/admin/refunds'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-3">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => number_format($value),
                'tone' => $tone,
                'link' => $link,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach (
        [
            '' => 'All',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'successful' => 'Successful',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ] as $value => $label
    ): ?>
        <a href="/admin/payments<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No payments recorded.',
    'columns'   => [
        ['key' => 'reference', 'label' => 'Reference', 'class' => 'small'],
        ['key' => 'gateway_code', 'label' => 'Gateway', 'class' => 'small text-muted'],
        ['key' => 'order_number', 'label' => 'Order', 'link' => '/admin/orders/{order_id}', 'class' => 'small'],
        ['key' => 'customer_name', 'label' => 'Customer', 'truncate' => 22, 'class' => 'small'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'align' => 'end'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'created_at', 'label' => 'Date', 'type' => 'datetime', 'class' => 'small text-muted'],
    ],
]); ?>