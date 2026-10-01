<?php

/**
 * Admin customers — /admin/customers
 *
 * @var \App\Paginator $paginator
 * @var string $search
 * @var string $status
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Customers <span class="text-muted fw-normal">(<?= number_format($paginator->total()) ?>)</span></h2>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-6">
            <input type="search" name="q" class="form-control" placeholder="Name, email or phone…" value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <select name="status" class="form-select">
                <option value="">Any status</option>
                <?php foreach (['active', 'pending', 'suspended', 'deactivated'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? ' selected' : '' ?>>
                        <?= e(ucfirst($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No customers matched your search.',
    'columns'   => [
        ['key' => 'full_name', 'label' => 'Customer', 'truncate' => 24],
        ['key' => 'email',     'label' => 'Email', 'truncate' => 28, 'class' => 'small text-muted'],
        ['key' => 'phone',     'label' => 'Phone', 'class' => 'small text-muted'],
        ['key' => 'order_count', 'label' => 'Orders', 'type' => 'number', 'align' => 'end'],
        ['key' => 'spent',     'label' => 'Spent', 'type' => 'money', 'align' => 'end'],
        ['key' => 'created_at', 'label' => 'Joined', 'type' => 'date'],
        ['key' => 'status',    'label' => 'Status', 'type' => 'badge', 'link' => '/admin/customers/{id}'],
    ],
    'actions'   => [
        ['label' => 'View profile', 'icon' => 'bi-eye', 'href' => '/admin/customers/{id}', 'variant' => 'btn-primary'],
    ],
]); ?>