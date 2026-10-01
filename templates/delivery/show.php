<?php

/**
 * Delivery detail — /delivery/orders/{id}
 *
 * @var array $delivery
 * @var array $items
 * @var array $history
 */
$address = $delivery['address_snapshot'] !== null
    ? json_decode((string) $delivery['address_snapshot'], true) : null;

// Only these moves are legal from the current state; the service enforces it too.
$next = [
    'assigned'   => [['picked_up', 'Mark as picked up', 'btn-primary', 'bi-box-arrow-up']],
    'picked_up'  => [['in_transit', 'Start delivery', 'btn-primary', 'bi-truck']],
    'in_transit' => [
        ['delivered',      'Mark as delivered',      'btn-success', 'bi-check2-circle'],
        ['failed_delivery', 'Report a failed attempt', 'btn-outline-danger', 'bi-x-circle'],
    ],
][(string) $delivery['status']] ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Delivery #<?= (int) $delivery['id'] ?> <?= status_badge((string) $delivery['status']) ?></h2>
        <p class="text-muted small mb-0">
            <?= e((string) ($delivery['sub_order_number'] ?? $delivery['order_number'])) ?>
            · <?= e((string) $delivery['pharmacy_name']) ?>
        </p>
    </div>
    <a href="/delivery/orders" class="btn btn-sm btn-light">Back to list</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items -->
        <div class="ipl-card mb-3">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Items in this drop (<?= count($items) ?>)</h3>
                <?php foreach ($items as $item): ?>
                    <div class="d-flex align-items-center gap-2 py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <div style="width:40px;height:40px;flex:0 0 40px;border-radius:8px;background:#f2f7f5;overflow:hidden">
                            <img src="<?= e(upload_url($item['image'])) ?>" alt="" class="w-100 h-100"
                                style="object-fit:contain;padding:.2rem" loading="lazy">
                        </div>
                        <span class="small fw-semibold flex-grow-1"><?= e((string) $item['product_name']) ?></span>
                        <span class="chip">× <?= (int) $item['quantity'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Status updates -->
        <?php if (!empty($next)): ?>
            <div class="ipl-card mb-3" style="border-color:var(--ipl-primary)">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3">Update status</h3>

                    <?php foreach ($next as [$status, $label, $btnClass, $icon]): ?>
                        <form method="post" action="/delivery/orders/<?= (int) $delivery['id'] ?>/status"
                            enctype="multipart/form-data" class="mb-3 pb-3"
                            style="border-bottom:1px dashed var(--ipl-border)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($status) ?>">

                            <div class="mb-2">
                                <label class="form-label small" for="note_<?= e($status) ?>">
                                    <?= $status === 'delivered' ? 'Delivery note' : 'Reason' ?>
                                    <?= $status === 'failed_delivery' ? '(required)' : '' ?>
                                </label>
                                <textarea name="note" id="note_<?= e($status) ?>" rows="2" class="form-control"
                                    placeholder="<?= $status === 'delivered'
                                                        ? 'e.g. Handed to Mrs Adeyemi at the gate'
                                                        : 'e.g. Customer not available, no answer on the phone' ?>"></textarea>
                            </div>

                            <?php if ($status === 'delivered'): ?>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small" for="proof_image">Proof of delivery photo</label>
                                        <input type="file" name="proof_image" id="proof_image"
                                            class="form-control form-control-sm" accept="image/*">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Capture GPS (optional)</label>
                                        <button type="button" class="btn btn-sm btn-light w-100" data-delivery-geo>
                                            <i class="bi bi-geo-alt me-1"></i>Get my location
                                        </button>
                                        <input type="hidden" name="latitude" value="">
                                        <input type="hidden" name="longitude" value="">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn <?= e($btnClass) ?> w-100">
                                <i class="bi <?= e($icon) ?> me-1"></i> <?= e($label) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="ipl-card mb-3">
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        This delivery is <?= e(str_replace('_', ' ', (string) $delivery['status'])) ?>.
                        No further updates are needed.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- History -->
        <div class="ipl-card">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Progress</h3>
                <?php if (empty($history)): ?>
                    <p class="small text-muted mb-0">No status changes recorded yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($history as $entry): ?>
                            <div class="timeline-item is-done">
                                <div class="small fw-semibold">
                                    <?= e(ucfirst(str_replace('_', ' ', (string) $entry['to_status']))) ?>
                                </div>
                                <?php if (!empty($entry['note'])): ?>
                                    <div class="text-muted small"><?= e((string) $entry['note']) ?></div>
                                <?php endif; ?>
                                <div class="when"><?= e(date('j M Y H:i', strtotime((string) $entry['created_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2"><i class="bi bi-person me-1"></i>Customer</h3>
            <div class="small">
                <div class="fw-semibold"><?= e((string) $delivery['customer_name']) ?></div>
                <a href="tel:<?= e((string) $delivery['customer_phone']) ?>">
                    <i class="bi bi-telephone me-1"></i><?= e((string) $delivery['customer_phone']) ?>
                </a>
            </div>

            <?php if (!empty($delivery['customer_note'])): ?>
                <div class="alert alert-info small mt-3 mb-0">
                    <strong>Customer note:</strong> <?= e((string) $delivery['customer_note']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2"><i class="bi bi-geo-alt me-1"></i>Deliver to</h3>
            <?php if (is_array($address)): ?>
                <div class="small">
                    <div class="fw-semibold"><?= e((string) ($address['recipient_name'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['address_line'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                    <?php if (!empty($address['landmark'])): ?>
                        <div class="fst-italic"><?= e((string) $address['landmark']) ?></div>
                    <?php endif; ?>
                </div>
                <a href="https://maps.google.com/?q=<?= urlencode((string) ($address['latitude'] ?? '') . ',' . (string) ($address['longitude'] ?? '')) ?>"
                    target="_blank" rel="noopener" class="btn btn-soft btn-sm w-100 mt-2">
                    <i class="bi bi-map me-1"></i>Open in Maps
                </a>
            <?php else: ?>
                <p class="small text-muted mb-0">No address recorded for this delivery.</p>
            <?php endif; ?>
        </div>

        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2"><i class="bi bi-shop me-1"></i>Collect from</h3>
            <div class="small">
                <div class="fw-semibold"><?= e((string) $delivery['pharmacy_name']) ?></div>
                <div class="text-muted"><?= e((string) $delivery['pharmacy_address']) ?></div>
                <a href="tel:<?= e((string) $delivery['pharmacy_phone']) ?>">
                    <i class="bi bi-telephone me-1"></i><?= e((string) $delivery['pharmacy_phone']) ?>
                </a>
            </div>
        </div>

        <?php if (!empty($delivery['otp_code'])): ?>
            <div class="ipl-card p-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-shield-check me-1"></i>Delivery code</h3>
                <p class="small text-muted">
                    Ask the customer for this code before you hand over the order.
                </p>
                <div class="fs-4 fw-bold text-center letter-spacing" style="letter-spacing:.3em">
                    <?= e((string) $delivery['otp_code']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>