<?php

/**
 * Product detail page.
 *
 * @var array $product
 * @var float $price
 * @var float|null $discountPrice
 * @var int   $savePercent
 * @var bool  $storefrontVisible
 * @var bool  $inWishlist
 * @var array $related
 * @var array $otherSellers
 */
$inStock     = (int) $product['stock_qty'] > 0;
$final       = $discountPrice ?? $price;
$rx          = (int) $product['requires_prescription'] === 1;
$expirySoon  = $product['expiry_date'] !== null
    && strtotime((string) $product['expiry_date']) < strtotime('+6 months');
$images      = $product['images'] ?? [];
$mainImage   = $images[0]['file_path'] ?? null;
$reviewCount = (int) $product['rating_count'];
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/products">Products</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="/category/<?= e((string) $product['category_slug']) ?>"><?= e((string) $product['category_name']) ?></a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e((string) $product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- ================= Gallery ================= -->
        <div class="col-lg-5">
            <div class="pdp-gallery ipl-card p-3">
                <div class="main-img">
                    <img src="<?= e(upload_url($mainImage)) ?>" alt="<?= e((string) $product['name']) ?>"
                        data-gallery-main id="pdpMainImage">
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="pdp-thumbs">
                        <?php foreach ($images as $i => $image): ?>
                            <img src="<?= e(upload_url((string) $image['file_path'])) ?>"
                                alt="Product image <?= $i + 1 ?>"
                                class="<?= $i === 0 ? 'is-active' : '' ?>"
                                data-gallery-thumb="<?= e(upload_url((string) $image['file_path'])) ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= Buy box ================= -->
        <div class="col-lg-7">
            <?php if (!$storefrontVisible): ?>
                <div class="alert alert-warning d-flex gap-2 align-items-start">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>This product is not currently available for purchase.</strong>
                        It may have been paused by the pharmacy. Browse similar products below.
                    </div>
                </div>
            <?php endif; ?>

            <h1 class="h3 fw-bold mb-1"><?= e((string) $product['name']) ?></h1>

            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <?php if ($reviewCount > 0): ?>
                    <?= star_rating((float) $product['rating_avg'], $reviewCount) ?>
                <?php endif; ?>
                <?php if (!empty($product['brand_name'])): ?>
                    <span class="text-muted small">Brand: <strong><?= e((string) $product['brand_name']) ?></strong></span>
                <?php endif; ?>
                <span class="text-muted small">SKU: <?= e((string) $product['sku']) ?></span>
            </div>

            <div class="d-flex align-items-baseline gap-3 mb-1">
                <span class="fs-2 fw-bold"><?= money($final) ?></span>
                <?php if ($discountPrice !== null): ?>
                    <span class="price-old fs-5"><?= money($price) ?></span>
                    <span class="badge bg-danger">Save <?= $savePercent ?>%</span>
                <?php endif; ?>
            </div>
            <p class="text-muted small">Price set by <?= e((string) $product['pharmacy_name']) ?>. Inclusive of applicable taxes.</p>

            <?php if ($rx): ?>
                <div class="alert alert-warning d-flex gap-2 align-items-start small">
                    <i class="bi bi-file-earmark-medical fs-5"></i>
                    <div>
                        <strong>Prescription required.</strong>
                        This medicine is dispensed only after a licensed pharmacist reviews your prescription.
                        You will be asked to upload it at checkout — no prescription means no dispensing.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$inStock): ?>
                <div class="alert alert-danger d-flex gap-2 align-items-center">
                    <i class="bi bi-x-circle"></i> Currently out of stock at this pharmacy.
                </div>
            <?php endif; ?>

            <?php if ($inStock && $storefrontVisible): ?>
                <div class="d-flex flex-wrap gap-2 align-items-center my-3">
                    <div class="qty-control">
                        <button type="button" data-qty-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input type="number" value="1" min="1" max="<?= min(99, (int) $product['stock_qty']) ?>" id="pdpQty" aria-label="Quantity">
                        <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" id="pdpAddToCart"
                        data-add-to-cart="<?= (int) $product['id'] ?>">
                        <i class="bi bi-cart-plus me-1"></i> Add to cart
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-lg<?= $inWishlist ? ' is-active' : '' ?>"
                        data-wishlist="<?= (int) $product['id'] ?>" title="Add to wishlist">
                        <i class="bi bi-heart<?= $inWishlist ? '-fill' : '' ?>"></i>
                    </button>
                </div>
                <p class="text-muted small">
                    <i class="bi bi-box-seam me-1"></i><?= (int) $product['stock_qty'] ?> unit(s) in stock
                    <?php if ($expirySoon && $product['expiry_date'] !== null): ?>
                        · <i class="bi bi-calendar-event me-1"></i>Expires <?= e(date('j M Y', strtotime((string) $product['expiry_date']))) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="/checkout" class="btn btn-soft" <?= $inStock && $storefrontVisible ? '' : 'aria-disabled="true"' ?>>
                    <i class="bi bi-lightning-charge me-1"></i> Buy now
                </a>
                <a href="/pharmacy/<?= e((string) $product['pharmacy_slug']) ?>" class="btn btn-light">
                    <i class="bi bi-shop me-1"></i> Visit this pharmacy
                </a>
            </div>

            <!-- Pharmacy card -->
            <div class="ipl-card p-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(upload_url($product['pharmacy_logo'])) ?>" alt="" width="48" height="48"
                        class="rounded" style="object-fit:contain" onerror="this.src='/assets/images/placeholder.svg'">
                    <div class="flex-grow-1 min-w-0">
                        <a href="/pharmacy/<?= e((string) $product['pharmacy_slug']) ?>" class="fw-bold text-reset d-block text-truncate">
                            <?= e((string) $product['pharmacy_name']) ?>
                        </a>
                        <div class="small text-muted">
                            <i class="bi bi-geo-alt me-1"></i><?= e((string) $product['pharmacy_city']) ?>, <?= e((string) $product['pharmacy_state']) ?>
                        </div>
                    </div>
                    <?php if ((float) $product['pharmacy_rating'] > 0): ?>
                        <?= star_rating((float) $product['pharmacy_rating'], (int) $product['pharmacy_rating_count']) ?>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Product facts -->
            <div class="ipl-card p-3">
                <h2 class="h6 fw-bold mb-2">Product details</h2>
                <dl class="row mb-0 small">
                    <?php
                    $facts = [
                        'Generic name'   => $product['generic_name'],
                        'Brand'          => $product['brand_name'],
                        'Active ingredient' => $product['active_ingredient'],
                        'Strength'       => $product['strength'],
                        'Dosage form'    => $product['dosage_form'],
                        'Pack size'      => $product['pack_size'],
                        'Manufacturer'   => $product['manufacturer'],
                        'Batch number'   => $product['batch_number'],
                        'Expiry date'    => $product['expiry_date'] !== null ? date('j M Y', strtotime((string) $product['expiry_date'])) : null,
                    ];
                    foreach ($facts as $label => $value):
                        if ($value === null || $value === '') {
                            continue;
                        } ?>
                        <div class="info-row w-100">
                            <dt><?= e($label) ?></dt>
                            <dd><?= e((string) $value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <?php if (!empty($product['description'])): ?>
                    <hr>
                    <h3 class="h6 fw-bold">Description</h3>
                    <p class="small text-muted mb-0" style="white-space:pre-line"><?= e((string) $product['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================= Same product elsewhere ================= -->
    <?php if (!empty($otherSellers)): ?>
        <section class="mt-5">
            <h2 class="section-title h5 mb-3">Available at other pharmacies</h2>
            <div class="row g-3">
                <?php foreach ($otherSellers as $seller): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="ipl-card p-3 h-100">
                            <div class="small text-muted mb-1"><?= e((string) $seller['pharmacy_name']) ?></div>
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="fw-bold">
                                    <?= money(
                                        (float) ($seller['discount_price'] ?? 0) > 0
                                            && (float) $seller['discount_price'] < (float) $seller['price']
                                            ? (float) $seller['discount_price']
                                            : (float) $seller['price']
                                    ) ?>
                                </span>
                                <span class="text-muted small"><?= e((string) $seller['city']) ?></span>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-truck me-1"></i><?= money((float) $seller['delivery_fee']) ?> delivery ·
                                ~<?= (int) $seller['estimated_delivery_minutes'] ?> min
                            </div>
                            <a href="/product/<?= e((string) $seller['slug']) ?>" class="btn btn-soft btn-sm w-100 mt-2">
                                View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= Reviews ================= -->
    <section class="mt-5">
        <h2 class="section-title h5 mb-3">Customer reviews</h2>

        <?php if ($reviewCount > 0): ?>
            <div class="ipl-card p-3 mb-3 d-flex flex-wrap align-items-center gap-4">
                <div class="text-center">
                    <div class="fs-1 fw-bold"><?= number_format((float) $product['rating_avg'], 1) ?></div>
                    <?= star_rating((float) $product['rating_avg'], $reviewCount, false) ?>
                    <div class="text-muted small"><?= number_format($reviewCount) ?> review<?= $reviewCount === 1 ? '' : 's' ?></div>
                </div>
                <div class="flex-grow-1" style="min-width:220px">
                    <?php
                    $breakdown = [];
                    foreach ($product['rating_breakdown'] as $row) {
                        $breakdown[(int) $row['rating']] = (int) $row['total'];
                    }
                    for ($star = 5; $star >= 1; $star--):
                        $count  = $breakdown[$star] ?? 0;
                        $pct    = $reviewCount > 0 ? round($count / $reviewCount * 100) : 0; ?>
                        <div class="d-flex align-items-center gap-2 small mb-1">
                            <span class="text-muted" style="width:34px"><?= $star ?> ★</span>
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="text-muted" style="width:26px;text-align:right"><?= $count ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php if (\App\Auth::isCustomer()): ?>
                    <a href="/account/reviews/create?type=product&id=<?= (int) $product['id'] ?>" class="btn btn-soft btn-sm">
                        Write a review
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($product['reviews'])): ?>
            <div class="ipl-card empty-state py-4">
                <p class="mb-0">No reviews yet. Delivered customers can share their experience.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($product['reviews'] as $review): ?>
                    <div class="col-md-6">
                        <div class="ipl-card p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="brand-mark" style="width:34px;height:34px;font-size:.8rem">
                                    <i class="bi bi-person"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold small"><?= e((string) $review['author']) ?></div>
                                    <?= star_rating((float) $review['rating'], 0, false) ?>
                                </div>
                                <span class="text-muted small ms-auto">
                                    <?= e(date('j M Y', strtotime((string) $review['created_at']))) ?>
                                </span>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                                <h3 class="h6 fw-bold mb-1"><?= e((string) $review['title']) ?></h3>
                            <?php endif; ?>
                            <p class="small text-muted mb-0"><?= e((string) $review['body']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ================= Related ================= -->
    <?php if (!empty($related)): ?>
        <section class="mt-5 mb-4">
            <h2 class="section-title h5 mb-3">You may also like</h2>
            <div class="row g-3">
                <?php foreach (array_slice($related, 0, 8) as $relatedProduct): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <?php \App\View::include('components/product-card', ['product' => $relatedProduct]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('pdpAddToCart');
    var qty = document.getElementById('pdpQty');
    if (!button || !qty) return;
    button.dataset.quantity = qty.value;
    qty.addEventListener('change', function () { button.dataset.quantity = qty.value; });
});
</script>
