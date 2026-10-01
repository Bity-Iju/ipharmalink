<?php

/**
 * Admin products — /admin/products
 *
 * @var \App\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var int    $pharmacyId
 * @var int    $categoryId
 * @var array  $pharmacies
 * @var array  $categories
 * @var array  $stats
 */
$statuses = [
    '' => 'All',
    'awaiting' => 'Awaiting approval',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'out_of_stock' => 'Out of stock',
    'expired' => 'Expired',
];
?>
<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['All products',  (int) ($stats['total'] ?? 0),        'bi-box-seam',   'primary', ''],
        ['Awaiting approval', (int) ($stats['awaiting'] ?? 0), 'bi-hourglass-split', 'warning', 'awaiting'],
        ['Active',        (int) ($stats['active'] ?? 0),       'bi-check2-circle', 'success', 'active'],
        ['Out of stock',  (int) ($stats['out_of_stock'] ?? 0), 'bi-x-circle',   'danger',  'out_of_stock'],
        ['Expired',       (int) ($stats['expired'] ?? 0),      'bi-trash3',     'danger',  'expired'],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $filter]): ?>
        <div class="col-6 col-xl">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => number_format($value),
                'tone' => $tone,
                'link' => $filter === '' ? '/admin/products' : '/admin/products?status=' . $filter,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3">
            <label class="form-label" for="q">Search</label>
            <input type="search" name="q" id="q" class="form-control" placeholder="Name, SKU, generic…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <label class="form-label" for="pharmacy">Pharmacy</label>
            <select name="pharmacy" id="pharmacy" class="form-select">
                <option value="">All pharmacies</option>
                <?php foreach ($pharmacies as $pharmacy): ?>
                    <option value="<?= (int) $pharmacy['id'] ?>" <?= $pharmacyId === (int) $pharmacy['id'] ? ' selected' : '' ?>>
                        <?= e((string) $pharmacy['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="category">Category</label>
            <select name="category" id="category" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? ' selected' : '' ?>>
                        <?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select">
                <?php foreach ($statuses as $value => $label): ?>
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
    'empty'     => 'No products matched your filters.',
    'columns'   => [
        ['key' => 'name', 'label' => 'Product', 'truncate' => 34],
        ['key' => 'pharmacy_name', 'label' => 'Pharmacy', 'truncate' => 22, 'class' => 'small text-muted'],
        ['key' => 'category_name', 'label' => 'Category', 'class' => 'small text-muted'],
        ['key' => 'price', 'label' => 'Price', 'type' => 'money', 'align' => 'end'],
        ['key' => 'stock_qty', 'label' => 'Stock', 'type' => 'number', 'align' => 'end'],
        ['key' => 'is_approved', 'label' => 'Approved', 'type' => 'bool'],
    ],
    'actions'   => [
        ['label' => 'Manage', 'icon' => 'bi-pencil', 'href' => '/admin/products/{id}', 'variant' => 'btn-primary'],
        ['label' => 'View on site', 'icon' => 'bi-eye', 'href' => '/product/{slug}', 'variant' => 'btn-light'],
    ],
]); ?>