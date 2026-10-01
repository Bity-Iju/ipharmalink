<?php

/**
 * Manual stock adjustment — /pharmacy/inventory/adjust
 *
 * @var array $products
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-1">Adjust stock</h2>
            <p class="text-muted small mb-3">
                Record stock received from a supplier, or correct a miscount. Every change is
                written to the movement ledger with your name against it.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/pharmacy/inventory/adjust" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label required" for="product_id">Product</label>
                    <select name="product_id" id="product_id" class="form-select" required>
                        <option value="">Select a product…</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>" <?= (string) old('product_id') === (string) $product['id'] ? ' selected' : '' ?>>
                                <?= e(str_excerpt((string) $product['name'], 46)) ?>
                                — <?= (int) $product['stock_qty'] ?> in stock
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('product_id') ?>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="direction">Direction</label>
                        <select name="direction" id="direction" class="form-select" required>
                            <option value="in" <?= old('direction') === 'in' ? ' selected' : '' ?>>Stock in (received)</option>
                            <option value="out" <?= old('direction') === 'out' ? ' selected' : '' ?>>Stock out (removed)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="quantity">Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" required
                            min="1" value="<?= old('quantity', '1') ?>">
                        <?= $err('quantity') ?>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label required" for="reason">Reason</label>
                    <input type="text" name="reason" id="reason" class="form-control" required
                        placeholder="e.g. Delivery from Emzor Pharmacy, invoice 4021"
                        value="<?= old('reason') ?>">
                    <?= $err('reason') ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">Apply adjustment</button>
            </form>
        </div>
    </div>
</div>