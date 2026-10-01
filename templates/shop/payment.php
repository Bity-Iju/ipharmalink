<?php

/**
 * Payment method selection — /payment?order={id}
 *
 * @var array $order
 * @var array $gateways
 * @var array|null $existing
 */
$orderId = (int) $order['id'];
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="ipl-card p-4 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h1 class="h5 fw-bold mb-0">Complete your payment</h1>
                    <span class="chip"><?= e((string) $order['order_number']) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    Your order is reserved. Please pay within 30 minutes to avoid it being released.
                </p>
            </div>

            <?php if (empty($gateways)): ?>
                <div class="alert alert-warning">
                    <strong>No payment method is available.</strong>
                    Please contact support to complete your order.
                </div>
            <?php else: ?>
                <div class="ipl-card p-4 mb-3">
                    <h2 class="h6 fw-bold mb-3">Choose how to pay</h2>

                    <form method="post" action="/payment/start">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order" value="<?= $orderId ?>">

                        <div class="d-grid gap-2">
                            <?php foreach ($gateways as $gateway): ?>
                                <label class="method-option d-block">
                                    <input type="radio" name="gateway" class="d-none" value="<?= e((string) $gateway['code']) ?>"
                                        data-select-group="gateway"
                                        <?= $gateway === ($gateways[0] ?? null) ? 'checked' : '' ?>>
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi <?= match ($gateway['code']) {
                                                            'paystack'    => 'bi-credit-card-2-front',
                                                            'flutterwave' => 'bi-wallet2',
                                                            'bank_transfer' => 'bi-bank',
                                                            'cash_on_delivery' => 'bi-cash-coin',
                                                            default       => 'bi-credit-card',
                                                        } ?> fs-3 text-brand"></i>
                                        <div class="flex-grow-1">
                                            <strong><?= e((string) $gateway['name']) ?></strong>
                                            <div class="small text-muted">
                                                <?= match ($gateway['code']) {
                                                    'paystack'    => 'Card, bank transfer or USSD via Paystack',
                                                    'flutterwave' => 'Card, transfer or mobile money via Flutterwave',
                                                    'bank_transfer' => 'Transfer to our account, then confirm below',
                                                    'cash_on_delivery' => 'Pay in cash when your order arrives',
                                                    default => '',
                                                } ?>
                                            </div>
                                        </div>
                                        <?php if ((int) $gateway['is_sandbox'] === 1): ?>
                                            <span class="badge bg-warning">Test mode</span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">
                            <i class="bi bi-lock me-1"></i> Pay <?= money((float) $order['total']) ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($existing !== null): ?>
                <div class="ipl-card p-4">
                    <h2 class="h6 fw-bold mb-2">Already started a payment?</h2>
                    <p class="small text-muted">
                        You have a <?= e(str_replace('_', ' ', (string) $existing['status'])) ?> payment
                        with reference <code><?= e((string) $existing['reference']) ?></code>.
                    </p>
                    <form method="post" action="/payment/verify" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order" value="<?= $orderId ?>">
                        <button type="submit" class="btn btn-soft btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i> I have completed the payment
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="text-center small text-muted mt-3">
                <i class="bi bi-shield-check me-1"></i>
                Payments are verified directly with the gateway. Your order is only marked paid
                once the gateway confirms the transaction.
            </div>
        </div>
    </div>
</div>