<?php

/**
 * Payment failed — /payment/failed?order={id}
 *
 * @var array $order
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 text-center">
            <span class="brand-mark mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:#fee2e2;color:#b91c1c">
                <i class="bi bi-x-lg"></i>
            </span>
            <h1 class="h3 fw-bold mb-2">Payment not completed</h1>
            <p class="text-muted mb-4">
                Your order <strong><?= e((string) $order['order_number']) ?></strong> has been saved
                but not paid for. You can try again — your cart details are still here.
            </p>

            <div class="ipl-card p-4 text-start mb-3">
                <h2 class="h6 fw-bold mb-2">What to check</h2>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Make sure your card or bank account had enough balance.</li>
                    <li>Check that your bank did not block the transaction.</li>
                    <li>Try a different payment method if you have one.</li>
                    <li>If you were charged but it failed here, contact us with your order number.</li>
                </ul>
            </div>

            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="/payment?order=<?= (int) $order['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1"></i> Try another payment method
                </a>
                <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-light">View order</a>
            </div>

            <p class="small text-muted mt-4">
                Need help? <a href="/contact">Contact our support team</a> and quote
                <code><?= e((string) $order['order_number']) ?></code>.
            </p>
        </div>
    </div>
</div>