<?php

/**
 * Shopping cart — /cart
 *
 * @var array $contents  CartService::contents()
 * @var array $quote     OrderService::calculate('delivery')
 * @var string $couponCode
 */
$groups = $contents['groups'];
?>
<div class="container py-4">
    <h1 class="h3 section-title mb-1">Your cart</h1>
    <p class="text-muted">
        <?= (int) $contents['itemCount'] ?> item<?= (int) $contents['itemCount'] === 1 ? '' : 's' ?>
        from <?= count($groups) ?> pharmac<?= count($groups) === 1 ? 'y' : 'ies' ?>
    </p>

    <?php if (!empty($contents['problems'])): ?>
        <div class="alert alert-warning">
            <strong>Some changes were made to your cart:</strong>
            <ul class="mb-0 small">
                <?php foreach ($contents['problems'] as $problem): ?>
                    <li><?= e($problem) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($groups === []): ?>
        <div class="ipl-card empty-state">
            <div class="icon"><i class="bi bi-cart-x"></i></div>
            <h2 class="h5 fw-bold">Your cart is empty</h2>
            <p class="mb-3">Browse our catalogue and add the medicines you need.</p>
            <a href="/products" class="btn btn-primary btn-sm">Start shopping</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <?php foreach ($groups as $group): ?>
                    <div class="ipl-card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 pb-2 mb-2" style="border-bottom:1px solid var(--ipl-border)">
                                <img src="<?= e(upload_url($group['pharmacy_logo'])) ?>" alt="" width="32" height="32"
                                    style="object-fit:contain" onerror="this.src='/assets/images/placeholder.svg'">
                                <a href="/pharmacy/<?= e((string) $group['pharmacy_slug']) ?>" class="fw-semibold text-reset small">
                                    <?= e((string) $group['pharmacy_name']) ?>
                                </a>
                                <span class="text-muted small ms-auto"><?= e((string) $group['city']) ?></span>
                            </div>

                            <?php foreach ($group['items'] as $item): ?>
                                <div class="cart-line" data-cart-line>
                                    <a href="/product/<?= e((string) $item['slug']) ?>">
                                        <div class="thumb">
                                            <img src="<?= e(upload_url($item['image'])) ?>" alt="<?= e((string) $item['name']) ?>" loading="lazy">
                                        </div>
                                    </a>

                                    <div class="flex-grow-1 min-w-0">
                                        <a href="/product/<?= e((string) $item['slug']) ?>" class="text-reset">
                                            <h3 class="h6 fw-semibold mb-1"><?= e((string) $item['name']) ?></h3>
                                        </a>
                                        <?php if (!empty($item['generic_name'])): ?>
                                            <div class="text-muted small"><?= e((string) $item['generic_name']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-muted small">SKU: <?= e((string) $item['sku']) ?></div>

                                        <?php if ((int) $item['requires_prescription'] === 1): ?>
                                            <span class="badge bg-warning mt-1">
                                                <i class="bi bi-file-earmark-medical me-1"></i>Prescription required
                                            </span>
                                        <?php endif; ?>

                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                            <div class="qty-control">
                                                <button type="button" data-qty-step="-1" aria-label="Decrease">&minus;</button>
                                                <input type="number" value="<?= (int) $item['quantity'] ?>" min="1"
                                                    max="<?= min(99, (int) $item['stock_qty']) ?>"
                                                    data-cart-quantity="<?= (int) $item['product_id'] ?>"
                                                    aria-label="Quantity">
                                                <button type="button" data-qty-step="1" aria-label="Increase">+</button>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none"
                                                data-cart-remove="<?= (int) $item['product_id'] ?>">
                                                <i class="bi bi-trash me-1"></i>Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <div class="fw-bold" data-line-total><?= money((float) $item['line_total']) ?></div>
                                        <div class="text-muted small"><?= money((float) $item['unit_price']) ?> each</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between pt-2 mt-1">
                                <span class="text-muted small">Subtotal from this pharmacy</span>
                                <span class="fw-semibold"><?= money((float) $group['subtotal']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <a href="/products" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Continue shopping
                </a>
            </div>

            <!-- ================= Summary ================= -->
            <div class="col-lg-4">
                <div class="ipl-card p-3" style="position:sticky;top:90px">
                    <h2 class="h6 fw-bold mb-3">Order summary</h2>

                    <div class="summary-line">
                        <span class="text-muted">Subtotal</span>
                        <span data-cart-subtotal><?= money((float) $quote['subtotal']) ?></span>
                    </div>
                    <?php if ((float) $quote['tax_total'] > 0): ?>
                        <div class="summary-line">
                            <span class="text-muted">Tax</span>
                            <span><?= money((float) $quote['tax_total']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-line">
                        <span class="text-muted">Delivery</span>
                        <span><?= (float) $quote['delivery_fee'] > 0 ? money((float) $quote['delivery_fee']) : 'Calculated at checkout' ?></span>
                    </div>
                    <?php if ((float) $quote['discount_total'] > 0): ?>
                        <div class="summary-line text-success">
                            <span>Discount</span>
                            <span>−<?= money((float) $quote['discount_total']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-line summary-total">
                        <span>Total</span>
                        <span><?= money((float) ($quote['subtotal'] + $quote['tax_total'])) ?></span>
                    </div>
                    <p class="text-muted small mb-3">Delivery is confirmed at checkout.</p>

                    <?php if ($quote['requires_prescription']): ?>
                        <div class="alert alert-warning small py-2 px-3">
                            <i class="bi bi-file-earmark-medical me-1"></i>
                            Your cart contains prescription-only medicines. You will upload your
                            prescription at checkout for pharmacist review.
                        </div>
                    <?php endif; ?>

                    <?php if (count($groups) > 1): ?>
                        <div class="alert alert-info small py-2 px-3">
                            <i class="bi bi-info-circle me-1"></i>
                            You are shopping from <?= count($groups) ?> pharmacies. We will split this
                            into one order per pharmacy, and you will pay once.
                        </div>
                    <?php endif; ?>

                    <a href="/checkout" class="btn btn-primary w-100 btn-lg mb-2">
                        Proceed to checkout <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <form method="post" action="/cart/coupon" class="mt-3">
                        <?= csrf_field() ?>
                        <label class="form-label small" for="coupon">Promo code</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="coupon" id="coupon" class="form-control"
                                placeholder="Enter code" value="<?= e($couponCode) ?>">
                            <button class="btn btn-outline-primary" type="submit">Apply</button>
                        </div>
                    </form>
                    <?php if ($couponCode !== ''): ?>
                        <form method="post" action="/cart/coupon/remove" class="mt-1">
                            <?= csrf_field() ?>
                            <button class="btn btn-link btn-sm text-danger p-0">Remove promo code</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>