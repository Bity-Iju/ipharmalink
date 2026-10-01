<?php

/**
 * Admin commissions — /admin/commissions
 *
 * @var \App\Paginator $paginator
 * @var array $totals
 */
?>
<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Commission earned',  money((float) ($totals['commission_earned'] ?? 0)),   'bi-percent',   'success'],
        ['Commission pending', money((float) ($totals['commission_pending'] ?? 0)),  'bi-hourglass', 'warning'],
        ['Commission reversed', money((float) ($totals['commission_reversed'] ?? 0)), 'bi-arrow-counterclockwise', 'danger'],
        ['Pending payouts',   money((float) ($totals['pending_payouts'] ?? 0)),    'bi-cash-stack', 'info'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone]): ?>
        <div class="col-6 col-xl-3">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No commission records yet.',
    'columns'   => [
        ['key' => 'pharmacy_name', 'label' => 'Pharmacy', 'truncate' => 24],
        ['key' => 'order_number', 'label' => 'Order', 'link' => '/admin/orders/{order_id}', 'class' => 'small'],
        ['key' => 'base_amount', 'label' => 'Base', 'type' => 'money', 'align' => 'end'],
        ['key' => 'rate_percent', 'label' => 'Rate', 'type' => 'number', 'align' => 'end'],
        ['key' => 'commission_amount', 'label' => 'Commission', 'type' => 'money', 'align' => 'end'],
        ['key' => 'pharmacy_earnings', 'label' => 'Pharmacy net', 'type' => 'money', 'align' => 'end'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
]); ?>