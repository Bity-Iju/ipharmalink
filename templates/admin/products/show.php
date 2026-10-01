<?php

/**
 * Admin product detail / moderation — /admin/products/{id}
 *
 * @var array $product
 * @var array $pharmacy
 * @var array $images
 * @var array $movements
 * @var array $sales
 * @var array $categories
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$classes = [
    'otc' => 'Over-the-Counter',
    'prescription' => 'Prescription required',
    'restricted' => 'Restricted',
    'device' => 'Medical device',
    'supplement' => 'Supplement',
    'cosmetic' => 'Cosmetic',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-0"><?= e((string) $product['name']) ?></h2>
        <div class="text-muted small">
            SKU <?= e((string) $product['sku']) ?>
            · <a href="/admin/pharmacies/<?= (int) $product['pharmacy_id'] ?>">
                <?= e((string) ($pharmacy['name'] ?? '—')) ?></a>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/product/<?= e((string) $product['slug']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light">
            <i class="bi bi-box-arrow-up-right"></i> View
        </a>
        <form method="post" action="/admin/products/<?= (int) $product['id'] ?>/delete" class="m-0"
            data-confirm="Remove this product from the platform?">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger">Remove</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Price',  money((float) $product['price']),  'bi-currency-naira', 'primary', ''],
        ['Stock',  (int) $product['stock_qty'],       'bi-stack',         'success', ''],
        ['Units sold', (int) $sales['units'],        'bi-bag-check',     'info',    ''],
        ['Revenue', money((float) $sales['revenue']), 'bi-graph-up',     'warning', ''],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-3">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
                'link' => $link ?: null,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="ipl-card p-4 mb-3">
            <h2 class="h6 fw-bold mb-3">Moderate product</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/products/<?= (int) $product['id'] ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="name">Name</label>
                        <input type="text" name="name" id="name" class="form-control" required
                            value="<?= e((string) $product['name']) ?>">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="price">Price (₦)</label>
                        <input type="number" name="price" id="price" class="form-control" required min="0" step="0.01"
                            value="<?= e((string) $product['price']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="discount_price">Discount price (₦)</label>
                        <input type="number" name="discount_price" id="discount_price" class="form-control"
                            min="0" step="0.01" value="<?= e((string) ($product['discount_price'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="stock_qty">Stock</label>
                        <input type="number" name="stock_qty" id="stock_qty" class="form-control" required min="0"
                            value="<?= (int) $product['stock_qty'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="product_class">Class</label>
                        <select name="product_class" id="product_class" class="form-select" required>
                            <?php foreach ($classes as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $product['product_class'] === $value ? ' selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Uncategorised</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) $product['category_id'] === (int) $category['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="image">Replace image</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_approved" value="1"
                                        id="is_approved" <?= (int) $product['is_approved'] === 1 ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="is_approved">Approved to list</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                        id="is_active" <?= (int) $product['is_active'] === 1 ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" value="1"
                                        id="is_visible" <?= (int) $product['is_visible'] === 1 ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="is_visible">Visible in storefront</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="requires_prescription" value="1"
                                        id="requires_prescription" <?= (int) $product['requires_prescription'] === 1 ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="requires_prescription">Prescription required</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="ipl-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Stock movements (last 40)</h2>
                <?php if (empty($movements)): ?>
                    <p class="small text-muted mb-0">No movements recorded.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-end">Change</th>
                                    <th class="text-end">After</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $movement): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(date('j M H:i', strtotime((string) $movement['created_at']))) ?></td>
                                        <td class="small"><?= e(str_replace('_', ' ', (string) $movement['type'])) ?></td>
                                        <td class="text-end small fw-semibold <?= (int) $movement['change_qty'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= (int) $movement['change_qty'] >= 0 ? '+' : '' ?><?= (int) $movement['change_qty'] ?>
                                        </td>
                                        <td class="text-end small"><?= (int) $movement['new_qty'] ?></td>
                                        <td class="small text-muted"><?= e((string) ($movement['user_name'] ?? 'System')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Images</h2>
            <?php if (empty($images)): ?>
                <p class="small text-muted mb-0">No images uploaded.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($images as $image): ?>
                        <img src="<?= e(upload_url((string) $image['file_path'])) ?>" alt=""
                            width="70" height="70" class="rounded border" style="object-fit:contain">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ipl-card p-3">
            <h2 class="h6 fw-bold mb-2">Performance</h2>
            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Views</span><strong><?= (int) $product['views'] ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Units sold</span><strong><?= (int) $sales['units'] ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Revenue</span><strong><?= money((float) $sales['revenue']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Rating</span>
                    <strong><?= number_format((float) $product['rating_avg'], 1) ?> (<?= (int) $product['rating_count'] ?>)</strong>
                </div>
            </div>
        </div>
    </div>
</div>