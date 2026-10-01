<?php

/**
 * Order placed — /checkout/success/{orderNumber}
 *
 * @var array $order      full order with slices, items and history
 * @var array|null $payment
 * @var bool $needsPayment
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <span class="brand-mark mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:#d1fae5;color:#047857">
                    <i class="bi bi-check-lg"></i>
                </span>
                <h1 class="h3 fw-bold mb-2">Thank you for your order</h1>
                <p class="text-muted">
                    Order <strong><?= e((string) $order['order_number']) ?></strong> has been created.
                </p>
            </div>

            <?php if ($needsPayment): ?>
                <div class="alert alert-warning">
                    <strong>Payment is still pending.</strong>
                    Complete payment to confirm your order — the pharmacy will start preparing it once payment clears.
                    <div class="mt-2">
                        <a href="/payment?order=<?= (int) $order['id'] ?>" class="btn btn-primary btn-sm">
                            Pay <?= money((float) $order['total']) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($order['slices']) > 1): ?>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    This order was split into <?= count($order['slices']) ?> sub-orders, one per pharmacy.
                    Each pharmacy will prepare and deliver their part independently.
                </div>
            <?php endif; ?>

            <div class="ipl-card p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Order summary</h2>

                <?php foreach ($order['slices'] as $slice): ?>
                    <div class="pb-3 mb-3" style="border-bottom:1px dashed var(--ipl-border)">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= e((string) $slice['pharmacy_name']) ?></strong>
                                <div class="text-muted small"><?= e((string) $slice['sub_order_number']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold"><?= money((float) $slice['total']) ?></div>
                                <div class="small"><?= status_badge((string) $slice['status']) ?></div>
                            </div>
                        </div>

                        <?php foreach (array_filter($order['items'], static fn(array $i): bool => (int) $i['pharmacy_id'] === (int) $slice['pharmacy_id']) as $item): ?>
                            <div class="d-flex justify-content-between small py-1">
                                <span class="text-muted"><?= (int) $item['quantity'] ?> × <?= e((string) $item['product_name']) ?></span>
                                <span><?= money((float) $item['line_total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="summary-line"><span class="text-muted">Subtotal</span><span><?= money((float) $order['subtotal']) ?></span></div>
                <?php if ((float) $order['tax_total'] > 0): ?>
                    <div class="summary-line"><span class="text-muted">Tax</span><span><?= money((float) $order['tax_total']) ?></span></div>
                <?php endif; ?>
                <div class="summary-line"><span class="text-muted">Delivery</span><span><?= money((float) $order['delivery_fee']) ?></span></div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="summary-line text-success"><span>Discount</span><span>−<?= money((float) $order['discount_total']) ?></span></div>
                <?php endif; ?>
                <div class="summary-line summary-total"><span>Total</span><span><?= money((float) $order['total']) ?></span></div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-box-seam me-1"></i> Track this order
                </a>
                <a href="/account/orders/<?= (int) $order['id'] ?>/receipt" class="btn btn-light">
                    <i class="bi bi-receipt me-1"></i> Download receipt
                </a>
                <a href="/products" class="btn btn-light">Continue shopping</a>
            </div>
        </div>
    </div>
</div>