<?php

/**
 * Admin order detail (read-only oversight) — /admin/orders/{id}
 *
 * @var array $order
 */
$address = $order['address_snapshot'] !== null ? json_decode((string) $order['address_snapshot'], true) : null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">
            <?= e((string) $order['order_number']) ?>
            <?= status_badge((string) $order['status']) ?>
            <?= status_badge((string) $order['payment_status']) ?>
        </h2>
        <p class="text-muted small mb-0">
            Placed <?= e(date('j F Y H:i', strtotime((string) $order['created_at']))) ?>
            · <span class="text-capitalize"><?= e((string) $order['fulfilment_method']) ?></span>
        </p>
    </div>
    <a href="/admin/orders" class="btn btn-sm btn-light">Back to orders</a>
</div>

<div class="alert alert-info small">
    <i class="bi bi-eye me-1"></i>
    Oversight view — this page is read-only. Operational changes are made by the pharmacy,
    the rider or the customer, and are recorded in the audit log.
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php foreach ($order['slices'] as $slice):
            $sliceItems = array_values(array_filter(
                $order['items'],
                static fn(array $i): bool => (int) $i['pharmacy_id'] === (int) $slice['pharmacy_id']
            )); ?>
            <div class="ipl-card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2 mb-2"
                        style="border-bottom:1px solid var(--ipl-border)">
                        <div>
                            <a href="/admin/pharmacies/<?= (int) $slice['pharmacy_id'] ?>" class="fw-semibold text-reset">
                                <?= e((string) $slice['pharmacy_name']) ?>
                            </a>
                            <div class="text-muted small"><?= e((string) $slice['sub_order_number']) ?></div>
                        </div>
                        <div class="text-end">
                            <?= status_badge((string) $slice['status']) ?>
                            <div class="fw-semibold mt-1"><?= money((float) $slice['total']) ?></div>
                        </div>
                    </div>

                    <?php foreach ($sliceItems as $item): ?>
                        <div class="d-flex justify-content-between small py-1">
                            <span class="text-muted">
                                <?= (int) $item['quantity'] ?> × <?= e((string) $item['product_name']) ?>
                                <?php if ((int) $item['requires_prescription'] === 1): ?>
                                    <span class="badge bg-warning">Rx</span>
                                <?php endif; ?>
                            </span>
                            <span><?= money((float) $item['line_total']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="small mt-2 pt-2" style="border-top:1px dashed var(--ipl-border)">
                        <span class="text-muted">Commission</span>
                        <span class="text-danger">−<?= money((float) $slice['platform_fee']) ?></span>
                        <span class="text-muted ms-3">Pharmacy earnings</span>
                        <span class="fw-semibold text-success"><?= money((float) $slice['pharmacy_earnings']) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

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
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2">Timeline</h3>
            <div class="timeline">
                <?php foreach ($order['history'] as $entry): ?>
                    <div class="timeline-item is-done">
                        <div class="small fw-semibold">
                            <?= e(ucfirst(str_replace('_', ' ', (string) $entry['to_status']))) ?>
                        </div>
                        <?php if (!empty($entry['note'])): ?>
                            <div class="text-muted small"><?= e(str_excerpt((string) $entry['note'], 70)) ?></div>
                        <?php endif; ?>
                        <div class="when">
                            <?= e(date('j M Y H:i', strtotime((string) $entry['created_at']))) ?>
                            <?php if (!empty($entry['actor_name'])): ?>
                                · <?= e((string) $entry['actor_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (is_array($address)): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-geo-alt me-1"></i>Address</h3>
                <div class="small">
                    <div class="fw-semibold"><?= e((string) ($address['recipient_name'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['address_line'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($order['deliveries'])): ?>
            <div class="ipl-card p-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-truck me-1"></i>Delivery</h3>
                <?php foreach ($order['deliveries'] as $delivery): ?>
                    <div class="small mb-2">
                        <?= status_badge((string) $delivery['status']) ?>
                        <?php if (!empty($delivery['personnel_name'])): ?>
                            <div class="text-muted mt-1">Rider: <?= e((string) $delivery['personnel_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($delivery['tracking_number'])): ?>
                            <div class="text-muted">Tracking: <?= e((string) $delivery['tracking_number']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>