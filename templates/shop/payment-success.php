<?php

/**
 * Payment confirmed — /payment/success?order={id}
 *
 * Shows the truth from the database, not from the fact the browser was
 * redirected here.
 *
 * @var array $order
 * @var bool  $confirmed
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 text-center">

            <?php if ($confirmed): ?>
                <div class="mb-4">
                    <span class="brand-mark mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:#d1fae5;color:#047857">
                        <i class="bi bi-check-lg"></i>
                    </span>
                    <h1 class="h3 fw-bold mb-2">Payment successful</h1>
                    <p class="text-muted">Thank you! Your order has been sent to the pharmacy.</p>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <span class="brand-mark mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:#fef3c7;color:#b45309">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                    <h1 class="h3 fw-bold mb-2">Confirming your payment</h1>
                    <p class="text-muted">
                        We are waiting for the payment provider to confirm your transaction.
                        This normally takes a few seconds.
                    </p>
                </div>
            <?php endif; ?>

            <div class="ipl-card p-4 text-start mb-3">
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Order number</span>
                    <span class="fw-semibold"><?= e((string) $order['order_number']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Payment status</span>
                    <span><?= status_badge((string) $order['payment_status']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Amount</span>
                    <span class="fw-bold"><?= money((float) $order['total']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Fulfilment</span>
                    <span class="text-capitalize"><?= e((string) $order['fulfilment_method']) ?></span>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-receipt me-1"></i> View your order
                </a>
                <a href="/products" class="btn btn-light">Continue shopping</a>
            </div>

            <?php if (!$confirmed): ?>
                <p class="small text-muted mt-4">
                    If your card was charged but this page still shows &ldquo;confirming&rdquo;,
                    <a href="/account/orders/<?= (int) $order['id'] ?>">check your order</a> or contact support.
                    Your order is only marked paid once the provider confirms the transaction.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>