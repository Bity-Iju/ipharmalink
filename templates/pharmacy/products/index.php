<?php

/**
 * Product list — /pharmacy/products
 *
 * @var \App\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var int    $categoryId
 * @var array  $categories
 */
$products = $paginator->items();

$statusFilters = [
    '' => 'All',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'low_stock' => 'Low stock',
    'out_of_stock' => 'Out of stock',
    'expiring' => 'Expiring',
    'expired' => 'Expired',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Products <span class="text-muted fw-normal">(<?= number_format($paginator->total()) ?>)</span></h2>
    <a href="/pharmacy/products/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add product
    </a>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-5">
            <label class="form-label" for="q">Search</label>
            <input type="search" name="q" id="q" class="form-control" placeholder="Name, SKU, generic or brand…"
                value="<?= e($search) ?>">
        </div>
        <div class="col-lg-3">
            <label class="form-label" for="category">Category</label>
            <select name="category" id="category" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <?php if ($category['parent_id'] !== null) {
                        continue;
                    } ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? ' selected' : '' ?>>
                        <?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select">
                <?php foreach ($statusFilters as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if ($products === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-box"></i></div>
        <h3 class="h5 fw-bold">No products found</h3>
        <p class="mb-3">Add your first product or adjust the filters.</p>
        <a href="/pharmacy/products/create" class="btn btn-primary btn-sm">Add product</a>
    </div>
<?php else: ?>
    <!-- Bulk actions -->
    <form method="post" action="/pharmacy/products/bulk" class="ipl-card p-2 mb-3" id="bulkForm">
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <select name="bulk_action" class="form-select form-select-sm" style="width:auto" id="bulkAction">
                <option value="">Bulk action…</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="feature">Mark featured</option>
                <option value="unfeature">Remove featured</option>
                <option value="stock">Update stock</option>
                <option value="price">Update price</option>
                <option value="delete">Delete</option>
            </select>

            <input type="number" name="stock_amount" class="form-control form-control-sm" style="width:110px"
                placeholder="Qty" min="0">
            <select name="stock_mode" class="form-select form-select-sm" style="width:auto">
                <option value="set">set</option>
                <option value="increase">add</option>
                <option value="decrease">subtract</option>
            </select>

            <input type="number" name="price_amount" class="form-control form-control-sm" style="width:130px"
                placeholder="Amount (₦)" min="0" step="0.01">
            <select name="price_mode" class="form-select form-select-sm" style="width:auto">
                <option value="set">set</option>
                <option value="increase">add</option>
                <option value="decrease">subtract</option>
                <option value="percent">percent %</option>
            </select>

            <button class="btn btn-sm btn-dark" type="submit"
                data-confirm="Apply this bulk action to the selected products?">Apply</button>
        </div>
    </form>

    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="checkAll"></th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product):
                        $price = (float) $product['price'];
                        $discount = (float) ($product['discount_price'] ?? 0) > 0 && (float) $product['discount_price'] < $price
                            ? (float) $product['discount_price'] : null;
                        $stock = (int) $product['stock_qty'];
                        $low   = $stock > 0 && $stock <= (int) $product['min_stock_level']; ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="product_ids[]" value="<?= (int) $product['id'] ?>"
                                    form="bulkForm" class="row-check">
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= e(upload_url($product['image'])) ?>" alt="" width="40" height="40"
                                        class="rounded" style="object-fit:contain" loading="lazy">
                                    <div class="min-w-0">
                                        <a href="/pharmacy/products/edit/<?= (int) $product['id'] ?>" class="fw-semibold small text-reset d-block text-truncate">
                                            <?= e((string) $product['name']) ?>
                                        </a>
                                        <span class="text-muted small">SKU: <?= e((string) $product['sku']) ?></span>
                                        <?php if ((int) $product['requires_prescription'] === 1): ?>
                                            <span class="badge bg-warning ms-1">Rx</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small"><?= e((string) ($product['category_name'] ?? '—')) ?></td>
                            <td>
                                <span class="fw-semibold small">
                                    <?= money($discount ?? $price) ?>
                                </span>
                                <?php if ($discount !== null): ?>
                                    <div class="price-old"><?= money($price) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $stock === 0 ? 'danger' : ($low ? 'warning' : 'success') ?>">
                                    <?= $stock ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $product['is_active'] === 1): ?>
                                    <?= status_badge('active') ?>
                                <?php else: ?>
                                    <?= status_badge('inactive') ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="/product/<?= e((string) $product['slug']) ?>" class="btn btn-sm btn-light" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="/pharmacy/products/edit/<?= (int) $product['id'] ?>" class="btn btn-sm btn-light" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="/pharmacy/products/<?= (int) $product['id'] ?>/toggle" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-light" title="<?= (int) $product['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                            <i class="bi bi-<?= (int) $product['is_active'] === 1 ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="/pharmacy/products/<?= (int) $product['id'] ?>/duplicate" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-light" title="Duplicate">
                                            <i class="bi bi-files"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="/pharmacy/products/<?= (int) $product['id'] ?>/delete" class="m-0"
                                        data-confirm="Remove this product from your catalogue?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-light text-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'products']); ?>
<?php endif; ?>

<script>
    document.getElementById('checkAll')?.addEventListener('change', function(e) {
        document.querySelectorAll('.row-check').forEach(function(c) {
            c.checked = e.target.checked;
        });
    });
</script>