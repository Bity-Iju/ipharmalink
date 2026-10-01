<?php

/**
 * Admin staff overview — /admin/staff
 *
 * @var \App\Paginator $paginator
 * @var string $search
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Pharmacy owners &amp; staff</h2>
    <a href="/admin/pharmacies" class="btn btn-sm btn-light">Manage pharmacies</a>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-9">
            <input type="search" name="q" class="form-control" placeholder="Search by name, email or pharmacy…" value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <button class="btn btn-primary w-100">Search</button>
        </div>
    </div>
</form>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No pharmacy accounts found.',
    'columns'   => [
        ['key' => 'full_name',  'label' => 'Person', 'truncate' => 24],
        ['key' => 'email',      'label' => 'Email', 'truncate' => 28, 'class' => 'small text-muted'],
        ['key' => 'role_label', 'label' => 'Role', 'class' => 'small'],
        ['key' => 'pharmacy_name', 'label' => 'Pharmacy', 'truncate' => 24, 'class' => 'small text-muted'],
        ['key' => 'last_login_at', 'label' => 'Last login', 'type' => 'datetime', 'class' => 'small text-muted'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'actions'   => [
        ['label' => 'View pharmacy', 'icon' => 'bi-shop', 'href' => '/admin/pharmacies/{pharmacy_id}', 'variant' => 'btn-light'],
    ],
]); ?>