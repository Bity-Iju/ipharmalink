<?php

/**
 * Admin delivery personnel — /admin/delivery-personnel
 *
 * @var \App\Paginator $paginator
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Delivery personnel</h2>
    <a href="/admin/delivery-personnel/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add rider
    </a>
</div>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No delivery personnel registered.',
    'columns'   => [
        ['key' => 'full_name', 'label' => 'Rider', 'truncate' => 24],
        ['key' => 'email',     'label' => 'Email', 'truncate' => 28, 'class' => 'small text-muted'],
        ['key' => 'phone',     'label' => 'Phone', 'class' => 'small text-muted'],
        ['key' => 'vehicle_type', 'label' => 'Vehicle', 'class' => 'small'],
        ['key' => 'plate_number', 'label' => 'Plate', 'class' => 'small text-muted'],
        ['key' => 'active_deliveries', 'label' => 'Active', 'type' => 'number', 'align' => 'end'],
        ['key' => 'is_available', 'label' => 'Available', 'type' => 'bool'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
]); ?>