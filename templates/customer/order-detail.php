<?php

/**
 * Order detail — /account/orders/{id}
 *
 * @var array $order
 * @var array|null $payment
 * @var string|null $estimate
 * @var bool $canCancel
 */
$address = $order['address_snapshot'] !== null ? json_decode((string) $order['address_snapshot'], true) : null;
$fulfilmentLabels = [
    'pending_payment'   => 'Awaiting payment',
    'paid'              => 'Payment received',
    'received'          => 'Order received by the pharmacy',
    'processing'        => 'Being processed',
    'preparing'         => 'Being prepared',
    'ready_for_pickup'  => 'Ready for collection',
    'ready_for_delivery' => 'Ready for delivery',
    'out_for_delivery'  => 'Out for delivery',
    'delivered'         => 'Delivered',
    'cancelled'         => 'Cancelled',
    'refunded'          => 'Refunded',
];
$orderStatus = (string) $order['status'];
$currentStep = array_search($orderStatus, ['pending_payment', 'paid', 'received', 'processing', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered'], true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Order <?= e((string) $order['order_number']) ?></h2>
        <p class="text-muted small mb-0">
            Placed <?= e(date('j F Y \a\t H:i', strtotime((string) $order['created_at']))) ?>
            · <?= e(strtoupper((string) $order['currency'])) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/account/orders/<?= (int) $order['id'] ?>/track" class="btn btn-sm btn-light">
            <i class="bi bi-geo-alt me-1"></i> Track
        </a>
        <a href="/account/orders/<?= (int) $order['id'] ?>/receipt" class="btn btn-sm btn-light">
            <i class="bi bi-receipt me-1"></i> Receipt
        </a>
        <form method="post" action="/account/orders/<?= (int) $order['id'] ?>/reorder" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-light"><i class="bi bi-arrow-repeat me-1"></i>Reorder</button>
        </form>
    </div>
</div>

<!-- Status banner -->
<div class="ipl-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <?= status_badge($orderStatus) ?>
            <span class="ms-2 small text-muted">
                <?= e($fulfilmentLabels[$orderStatus] ?? ucfirst(str_replace('_', ' ', $orderStatus))) ?>
            </span>
        </div>
        <?php if ($estimate !== null): ?>
            <span class="chip"><i class="bi bi-truck"></i> Estimated arrival: <?= e($estimate) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!in_array($orderStatus, ['delivered', 'cancelled', 'refunded'], true)): ?>
        <div class="progress mt-3" style="height:6px">
            <?php
            $progress = [
                'pending_payment' => 10,
                'paid' => 20,
                'received' => 35,
                'processing' => 50,
                'preparing' => 65,
                'ready_for_pickup' => 80,
                'ready_for_delivery' => 80,
                'out_for_delivery' => 92,
                'delivered' => 100
            ];
            ?>
            <div class="progress-bar bg-success" style="width:<?= (int) ($progress[$orderStatus] ?? 10) ?>%"></div>
        </div>
    <?php endif; ?>
</div>

<?php if ($orderStatus === 'pending_payment'): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><strong>Payment required.</strong> Complete payment so the pharmacy can start preparing your order.</span>
        <a href="/payment?order=<?= (int) $order['id'] ?>" class="btn btn-primary btn-sm">Pay now</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items by pharmacy -->
        <?php foreach ($order['slices'] as $slice):
            $sliceItems = array_values(array_filter(
                $order['items'],
                static fn(array $i): bool => (int) $i['pharmacy_id'] === (int) $slice['pharmacy_id']
            ));
            $sliceHistory = array_values(array_filter(
                $order['history'],
                static fn(array $h): bool => $h['scope'] === 'pharmacy_order' && (int) ($h['scope_id'] ?? 0) === (int) $slice['id']
            ));
        ?>
            <div class="ipl-card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2 mb-2"
                        style="border-bottom:1px solid var(--ipl-border)">
                        <div>
                            <a href="/pharmacy/<?= e((string) $slice['pharmacy_slug']) ?>" class="fw-semibold text-reset">
                                <?= e((string) $slice['pharmacy_name']) ?>
                            </a>
                            <div class="text-muted small">
                                <?= e((string) $slice['sub_order_number']) ?>
                                <?php if ((int) $slice['delivery_available'] ?? 0): ?>
                                    · <i class="bi bi-geo-alt me-1"></i><?= e(str_excerpt((string) ($slice['address'] ?? ''), 46)) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <?= status_badge((string) $slice['status']) ?>
                            <div class="fw-semibold mt-1"><?= money((float) $slice['total']) ?></div>
                        </div>
                    </div>

                    <?php foreach ($sliceItems as $item): ?>
                        <div class="d-flex gap-2 py-2">
                            <div style="width:52px;height:52px;flex:0 0 52px;border-radius:10px;background:#f2f7f5;overflow:hidden">
                                <img src="<?= e(upload_url($item['image'])) ?>" alt="" class="w-100 h-100"
                                    style="object-fit:contain;padding:.25rem" loading="lazy">
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <?php if (!empty($item['product_slug'])): ?>
                                    <a href="/product/<?= e((string) $item['product_slug']) ?>" class="small fw-semibold text-reset">
                                        <?= e((string) $item['product_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="small fw-semibold"><?= e((string) $item['product_name']) ?></span>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <?= (int) $item['quantity'] ?> × <?= money((float) $item['unit_price']) ?>
                                </div>
                                <?php if ((int) $item['requires_prescription'] === 1): ?>
                                    <span class="badge bg-<?= $item['prescription_status'] === 'approved' ? 'success' : ($item['prescription_status'] === 'rejected' ? 'danger' : 'warning') ?> mt-1">
                                        Prescription: <?= e(ucfirst((string) $item['prescription_status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="fw-semibold small"><?= money((float) $item['line_total']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($sliceHistory)): ?>
                        <div class="mt-3 pt-2" style="border-top:1px dashed var(--ipl-border)">
                            <div class="timeline">
                                <?php foreach ($sliceHistory as $entry): ?>
                                    <div class="timeline-item is-done">
                                        <div class="small fw-semibold">
                                            <?= e(ucfirst(str_replace('_', ' ', (string) $entry['to_status']))) ?>
                                        </div>
                                        <?php if (!empty($entry['note'])): ?>
                                            <div class="text-muted small"><?= e((string) $entry['note']) ?></div>
                                        <?php endif; ?>
                                        <div class="when">
                                            <?= e(date('j M Y H:i', strtotime((string) $entry['created_at']))) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($slice['pharmacy_note'])): ?>
                        <div class="alert alert-info small mt-3 mb-0">
                            <strong>Note from <?= e((string) $slice['pharmacy_name']) ?>:</strong>
                            <?= e((string) $slice['pharmacy_note']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Totals -->
        <div class="ipl-card p-4">
            <h3 class="h6 fw-bold mb-3">Payment summary</h3>
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
    </div>

    <div class="col-lg-4">
        <!-- Delivery address -->
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2">
                <i class="bi bi-geo-alt me-1"></i>
                <?= (string) $order['fulfilment_method'] === 'pickup' ? 'Collection point' : 'Delivery address' ?>
            </h3>
            <?php if ($address !== null): ?>
                <div class="small">
                    <div class="fw-semibold"><?= e((string) ($address['recipient_name'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['address_line'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                    <?php if (!empty($address['phone'])): ?>
                        <div class="text-muted mt-1"><i class="bi bi-telephone me-1"></i><?= e((string) $address['phone']) ?></div>
                    <?php endif; ?>
                </div>
            <?php elseif ((string) $order['fulfilment_method'] === 'pickup'): ?>
                <p class="small text-muted mb-0">
                    Collect at the pharmacy. Check each pharmacy above for its address.
                </p>
            <?php else: ?>
                <p class="small text-muted mb-0">No delivery address was recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Delivery tracking -->
        <?php if (!empty($order['deliveries'])): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-truck me-1"></i>Delivery</h3>
                <?php foreach ($order['deliveries'] as $delivery): ?>
                    <div class="small">
                        <?= status_badge((string) $delivery['status']) ?>
                        <?php if (!empty($delivery['tracking_number'])): ?>
                            <div class="text-muted mt-1">Tracking: <?= e((string) $delivery['tracking_number']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($delivery['personnel_name'])): ?>
                            <div class="text-muted">Rider: <?= e((string) $delivery['personnel_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($delivery['delivery_note'])): ?>
                            <div class="text-muted fst-italic mt-1"><?= e((string) $delivery['delivery_note']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Confirm delivery -->
        <?php if (in_array($orderStatus, ['out_for_delivery', 'ready_for_delivery'], true)): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-2">Confirm you received this</h3>
                <form method="post" action="/account/orders/<?= (int) $order['id'] ?>/confirm-delivery">
                    <?= csrf_field() ?>
                    <?php
                    $otp = $order['deliveries'][0]['otp_code'] ?? null;
                    if ($otp !== null): ?>
                        <label class="form-label small" for="otp">Confirmation code from your rider</label>
                        <input type="text" name="otp" id="otp" class="form-control mb-2"
                            inputmode="numeric" maxlength="6" placeholder="6-digit code" required>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm w-100">Yes, I received my order</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Cancel -->
        <?php if ($canCancel): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-2">Need to cancel?</h3>
                <p class="small text-muted">
                    You can cancel while the pharmacy has not started preparing your order.
                </p>
                <form method="post" action="/account/orders/<?= (int) $order['id'] ?>/cancel"
                    data-confirm="Are you sure you want to cancel this order?">
                    <?= csrf_field() ?>
                    <input type="text" name="reason" class="form-control form-control-sm mb-2"
                        placeholder="Reason (optional)">
                    <button class="btn btn-outline-danger btn-sm w-100">Cancel this order</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Review -->
        <?php if ($orderStatus === 'delivered'): ?>
            <div class="ipl-card p-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-star me-1"></i>Rate this order</h3>
                <p class="small text-muted">Your feedback helps other customers shop confidently.</p>
                <a href="/account/reviews" class="btn btn-soft btn-sm w-100">Write a review</a>
            </div>
        <?php endif; ?>

        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-2">Need help?</h3>
            <div class="d-grid gap-2 small">
                <a href="/contact" class="btn btn-light btn-sm">Contact support</a>
                <a href="/pharmacy/<?= e((string) ($order['slices'][0]['pharmacy_slug'] ?? '')) ?>" class="btn btn-light btn-sm">
                    Contact the pharmacy
                </a>
            </div>
        </div>
    </div>
</div>