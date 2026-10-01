<?php

/**
 * Product card.
 *
 * @var array $product  row: id, slug, name, brand_name, pharmacy_name, pharmacy_slug,
 *                      price, discount_price, stock_qty, requires_prescription, image
 * @var bool  $compact  render a denser variant for carousels
 */
$product    = $product ?? [];
$compact    = $compact ?? false;
$price      = (float) ($product['price'] ?? 0);
$discount   = isset($product['discount_price']) && (float) $product['discount_price'] > 0 && (float) $product['discount_price'] < $price
    ? (float) $product['discount_price'] : null;
$final      = $discount ?? $price;
$inStock    = (int) ($product['stock_qty'] ?? 0) > 0;
$rx         = (int) ($product['requires_prescription'] ?? 0) === 1;
$image      = upload_url($product['image'] ?? null);
$save       = $discount !== null ? (int) round((1 - $discount / $price) * 100) : 0;
?>
<div class="product-card<?= $compact ? ' h-100' : '' ?>">
    <div class="thumb<?= $inStock ? '' : ' out-of-stock' ?>">
        <a href="/product/<?= e($product['slug'] ?? '') ?>" aria-label="<?= e($product['name'] ?? '') ?>">
            <img src="<?= e($image) ?>" alt="<?= e($product['name'] ?? '') ?>" loading="lazy">
        </a>

        <?php if ($rx): ?>
            <span class="rx-flag"><i class="bi bi-file-earmark-medical"></i> Rx</span>
        <?php endif; ?>
        <?php if ($save > 0): ?>
            <span class="badge bg-danger position-absolute bottom-0 start-0 m-2">-<?= $save ?>%</span>
        <?php endif; ?>

        <div class="thumb-actions">
            <button type="button" class="btn" title="Add to wishlist" data-wishlist="<?= (int) ($product['id'] ?? 0) ?>">
                <i class="bi bi-heart"></i>
            </button>
        </div>
    </div>

    <div class="body">
        <div class="pharmacy text-truncate">
            <i class="bi bi-shop me-1"></i><?= e($product['pharmacy_name'] ?? '') ?>
        </div>

        <a href="/product/<?= e($product['slug'] ?? '') ?>" class="text-reset">
            <div class="name"><?= e($product['name'] ?? '') ?></div>
        </a>

        <?php if (!empty($product['brand_name'])): ?>
            <div class="small text-muted"><?= e($product['brand_name']) ?><?= !empty($product['strength']) ? ' · ' . e($product['strength']) : '' ?></div>
        <?php endif; ?>

        <div class="d-flex align-items-baseline gap-2 mt-auto pt-2">
            <span class="price"><?= money($final) ?></span>
            <?php if ($discount !== null): ?>
                <span class="price-old"><?= money($price) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($inStock): ?>
            <button type="button" class="btn btn-primary btn-sm w-100 mt-2"
                data-add-to-cart="<?= (int) ($product['id'] ?? 0) ?>">
                <i class="bi bi-cart-plus me-1"></i> Add to cart
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-light btn-sm w-100 mt-2" disabled>
                <i class="bi bi-x-circle me-1"></i> Out of stock
            </button>
        <?php endif; ?>
    </div>
</div>