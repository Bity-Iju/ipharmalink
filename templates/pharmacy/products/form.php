<?php

/**
 * Product create / edit form — /pharmacy/products/create and /edit/{id}
 *
 * @var array|null $product
 * @var array      $images
 * @var array      $categories
 * @var array      $brands
 * @var array      $errors
 */
$isEdit  = $product !== null;
$err     = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$val = static function (string $field, mixed $default = '') use ($product): string {
    $old = \App\Session::old($field, null);
    if ($old !== null) {
        return (string) $old;
    }
    return (string) ($product[$field] ?? $default);
};
$checked = static function (string $field, bool $default = false) use ($product, $isEdit): bool {
    $old = \App\Session::old($field, null);
    if ($old !== null) {
        return in_array(strtolower((string) $old), ['1', 'on', 'yes', 'true'], true);
    }
    return $isEdit ? (int) ($product[$field] ?? 0) === 1 : $default;
};

/** Groups categories into top-level and subcategories. */
$topCategories    = array_values(array_filter($categories, static fn(array $c): bool => $c['parent_id'] === null));
$subByParent = [];
foreach ($categories as $category) {
    if ($category['parent_id'] !== null) {
        $subByParent[(int) $category['parent_id']][] = $category;
    }
}

$classes = [
    'otc' => 'Over-the-Counter (OTC)',
    'prescription' => 'Prescription required',
    'restricted' => 'Restricted medicine',
    'device' => 'Medical device',
    'supplement' => 'Supplement / vitamin',
    'cosmetic' => 'Cosmetic / personal care',
];
?>
<form method="post" action="<?= $isEdit ? '/pharmacy/products/' . (int) $product['id'] : '/pharmacy/products' ?>"
    enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Basics -->
            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Product details</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="name">Product name</label>
                        <input type="text" name="name" id="name" class="form-control" required
                            value="<?= e($val('name')) ?>" placeholder="e.g. Paracetamol 500mg Tablets">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="generic_name">Generic name</label>
                        <input type="text" name="generic_name" id="generic_name" class="form-control"
                            value="<?= e($val('generic_name')) ?>" placeholder="Paracetamol">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="brand_name">Brand name</label>
                        <input type="text" name="brand_name" id="brand_name" class="form-control"
                            value="<?= e($val('brand_name')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">Description</label>
                        <textarea name="description" id="description" rows="4" class="form-control" required
                            placeholder="What this product is, what it treats, and how it is taken."><?= e($val('description')) ?></textarea>
                        <div class="form-text">At least 20 characters.</div>
                        <?= $err('description') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="active_ingredient">Active ingredient</label>
                        <input type="text" name="active_ingredient" id="active_ingredient" class="form-control"
                            value="<?= e($val('active_ingredient')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="strength">Strength</label>
                        <input type="text" name="strength" id="strength" class="form-control"
                            placeholder="500mg" value="<?= e($val('strength')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="dosage_form">Dosage form</label>
                        <input type="text" name="dosage_form" id="dosage_form" class="form-control"
                            placeholder="Tablet" value="<?= e($val('dosage_form')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pack_size">Pack size</label>
                        <input type="text" name="pack_size" id="pack_size" class="form-control"
                            placeholder="20 tablets" value="<?= e($val('pack_size')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="manufacturer">Manufacturer</label>
                        <input type="text" name="manufacturer" id="manufacturer" class="form-control"
                            value="<?= e($val('manufacturer')) ?>">
                    </div>
                </div>
            </div>

            <!-- Classification -->
            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Classification</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="product_class">Product class</label>
                        <select name="product_class" id="product_class" class="form-select" required>
                            <?php foreach ($classes as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $val('product_class', 'otc') === $value ? ' selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('product_class') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-select" data-auto-submit>
                            <option value="">Uncategorised</option>
                            <?php foreach ($topCategories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= $val('category_id') === (string) $category['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="requires_prescription"
                                value="1" id="requires_prescription" <?= $checked('requires_prescription') ? ' checked' : '' ?>>
                            <label class="form-check-label" for="requires_prescription">
                                <strong>Prescription required</strong>
                                <span class="d-block small text-muted">
                                    Customers will upload a prescription and a pharmacist must approve it
                                    before this item is dispensed.
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images -->
            <div class="ipl-card p-4">
                <h2 class="h6 fw-bold mb-3">Images</h2>

                <?php if ($isEdit && !empty($images)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($images as $image): ?>
                            <img src="<?= e(upload_url((string) $image['file_path'])) ?>" alt=""
                                width="70" height="70" class="rounded border" style="object-fit:contain">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="form-label" for="image">Main image</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*">
                <div class="form-text">The first image becomes the product thumbnail. Max 5 MB.</div>

                <label class="form-label mt-3" for="gallery">Additional images</label>
                <input type="file" name="gallery[]" id="gallery" class="form-control" accept="image/*" multiple>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Pricing -->
            <div class="ipl-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Pricing</h2>
                <div class="mb-2">
                    <label class="form-label required" for="price">Selling price (₦)</label>
                    <input type="number" name="price" id="price" class="form-control" required min="0" step="0.01"
                        value="<?= e($val('price')) ?>">
                    <?= $err('price') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="discount_price">Discount price (₦)</label>
                    <input type="number" name="discount_price" id="discount_price" class="form-control"
                        min="0" step="0.01" value="<?= e($val('discount_price')) ?>">
                    <div class="form-text">Must be lower than the selling price.</div>
                </div>
                <div>
                    <label class="form-label" for="tax_rate">Tax rate (%)</label>
                    <input type="number" name="tax_rate" id="tax_rate" class="form-control"
                        min="0" max="100" step="0.01" value="<?= e($val('tax_rate', '0')) ?>">
                </div>
            </div>

            <!-- Stock -->
            <div class="ipl-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Stock &amp; batch</h2>
                <div class="mb-2">
                    <label class="form-label required" for="stock_qty">Quantity in stock</label>
                    <input type="number" name="stock_qty" id="stock_qty" class="form-control" required
                        min="0" value="<?= e($val('stock_qty', '0')) ?>">
                    <?= $err('stock_qty') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label required" for="min_stock_level">Low-stock alert at</label>
                    <input type="number" name="min_stock_level" id="min_stock_level" class="form-control" required
                        min="0" value="<?= e($val('min_stock_level', '5')) ?>">
                </div>
                <?php if ($isEdit): ?>
                    <div class="mb-2">
                        <label class="form-label" for="stock_reason">Stock change reason</label>
                        <input type="text" name="stock_reason" id="stock_reason" class="form-control"
                            placeholder="e.g. Stock count correction" maxlength="255">
                        <div class="form-text">Recorded in the movement ledger if the quantity changes.</div>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label" for="batch_number">Batch number</label>
                    <input type="text" name="batch_number" id="batch_number" class="form-control"
                        value="<?= e($val('batch_number')) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="manufacturing_date">Manufactured</label>
                    <input type="date" name="manufacturing_date" id="manufacturing_date" class="form-control"
                        value="<?= e($val('manufacturing_date')) ?>">
                </div>
                <div>
                    <label class="form-label" for="expiry_date">Expires</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control"
                        value="<?= e($val('expiry_date')) ?>">
                    <div class="form-text">Expired stock is hidden from customers automatically.</div>
                </div>
            </div>

            <!-- Visibility -->
            <div class="ipl-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Visibility</h2>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           <?= $isEdit ? ($checked('is_active') ? ' checked' : '') : 'checked' ?>>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="is_visible"
                        <?= $checked('is_visible', true) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_visible">Show in the storefront</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured"
                        <?= $checked('is_featured') ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_featured">Featured product</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-2">
                <?= $isEdit ? 'Save changes' : 'Add product' ?>
            </button>
            <a href="/pharmacy/products" class="btn btn-light w-100">Cancel</a>
        </div>
    </div>
</form>