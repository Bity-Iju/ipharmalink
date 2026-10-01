<?php

/**
 * Admin refunds — /admin/refunds
 *
 * @var \App\Paginator $paginator
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Refunds</h2>
    <a href="/admin/refunds/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New refund
    </a>
</div>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No refunds have been processed.',
    'columns'   => [
        ['key' => 'id', 'label' => '#', 'class' => 'small text-muted'],
        ['key' => 'order_number', 'label' => 'Order', 'link' => '/admin/orders/{order_id}', 'class' => 'small'],
        ['key' => 'customer_name', 'label' => 'Customer', 'truncate' => 22, 'class' => 'small'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'align' => 'end'],
        ['key' => 'reason', 'label' => 'Reason', 'truncate' => 40, 'class' => 'small text-muted'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'created_at', 'label' => 'Requested', 'type' => 'date', 'class' => 'small text-muted'],
    ],
]); ?>