<?php

/**
 * Admin pharmacies list — /admin/pharmacies and /admin/pharmacies/pending
 *
 * @var \App\Paginator $paginator
 * @var array  $counts
 * @var string $status
 * @var string $search
 * @var string $filter
 */
$statuses = [
    '' => 'All',
    'pending' => 'Pending',
    'approved' => 'Approved',
    'suspended' => 'Suspended',
    'rejected' => 'Rejected',
    'deactivated' => 'Deactivated',
];
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($statuses as $value => $label): ?>
        <a href="/admin/pharmacies<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= ($status === $value && $filter === '') ? ' bg-brand text-white' : '' ?>">
            <?= e($label) ?> (<?= (int) ($counts[$value] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-8">
            <input type="search" name="q" class="form-control"
                placeholder="Search by pharmacy, city, email or registration number…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-2">
            <select name="status" class="form-select">
                <?php foreach ($statuses as $value => $label): ?>
                    <?php if ($value === '') {
                        continue;
                    } ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php \App\View::include('admin/components/table', [
    'paginator' => $paginator,
    'empty'     => 'No pharmacies matched your search.',
    'columns'   => [
        ['key' => 'name',   'label' => 'Pharmacy', 'truncate' => 34],
        ['key' => 'owner_name', 'label' => 'Owner', 'truncate' => 22],
        ['key' => 'city',   'label' => 'Location', 'class' => 'small text-muted'],
        ['key' => 'product_count', 'label' => 'Products', 'type' => 'number', 'align' => 'end'],
        ['key' => 'order_count',   'label' => 'Orders',  'type' => 'number', 'align' => 'end'],
        ['key' => 'revenue', 'label' => 'Revenue', 'type' => 'compact', 'align' => 'end'],
        ['key' => 'status',  'label' => 'Status',  'type' => 'badge', 'link' => '/admin/pharmacies/{id}'],
    ],
    'actions'   => [
        ['label' => 'View details', 'icon' => 'bi-eye', 'href' => '/admin/pharmacies/{id}', 'variant' => 'btn-light'],
        ['label' => 'Products', 'icon' => 'bi-box-seam', 'href' => '/admin/pharmacies/{id}/products', 'variant' => 'btn-light'],
    ],
]); ?>