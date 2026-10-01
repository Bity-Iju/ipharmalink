<?php

/**
 * Admin pharmacy wallets — /admin/wallets
 *
 * @var \App\Paginator $paginator
 * @var array $totals
 */
?>
<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Total payable', money((float) ($totals['available'] ?? 0)), 'bi-wallet2', 'primary'],
        ['Pending',       money((float) ($totals['pending'] ?? 0)),   'bi-hourglass', 'warning'],
        ['Total paid out', money((float) ($totals['paid'] ?? 0)),   'bi-cash-stack', 'success'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone]): ?>
        <div class="col-md-4">
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
    'empty'     => 'No pharmacy wallets yet.',
    'columns'   => [
        ['key' => 'pharmacy_name', 'label' => 'Pharmacy', 'truncate' => 26, 'link' => '/admin/pharmacies/{pharmacy_id}'],
        ['key' => 'balance',        'label' => 'Available', 'type' => 'money', 'align' => 'end'],
        ['key' => 'pending_balance', 'label' => 'Pending',   'type' => 'money', 'align' => 'end'],
        ['key' => 'total_earned',   'label' => 'Lifetime',  'type' => 'money', 'align' => 'end'],
        ['key' => 'total_paid',     'label' => 'Paid out',  'type' => 'money', 'align' => 'end'],
    ],
    'actions'   => [
        ['label' => 'View pharmacy', 'icon' => 'bi-eye', 'href' => '/admin/pharmacies/{pharmacy_id}', 'variant' => 'btn-light'],
    ],
]); ?>