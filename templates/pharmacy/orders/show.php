<?php

/**
 * Pharmacy sub-order detail — /pharmacy/orders/{id}
 *
 * @var array $slice
 * @var array $history
 * @var array $prescriptions
 * @var array|null $delivery
 * @var array $riders
 * @var list<string> $actions
 */
$address = $slice['address'] ?? null;

$actionLabels = [
    'accept'   => ['Accept order', 'btn-primary', 'bi-check2'],
    'prepare'  => ['Start preparing', 'btn-primary', 'bi-hourglass-split'],
    'ready'    => ['Mark as ready', 'btn-primary', 'bi-bag-check'],
    'dispatch' => ['Dispatch', 'btn-primary', 'bi-truck'],
    'deliver'  => ['Mark delivered', 'btn-success', 'bi-check2-circle'],
    'cancel'   => ['Cancel order', 'btn-outline-danger', 'bi-x-circle'],
    'reject'   => ['Reject order', 'btn-outline-danger', 'bi-x-octagon'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">
            <?= e((string) $slice['sub_order_number']) ?>
            <?= status_badge((string) $slice['status']) ?>
        </h2>
        <p class="text-muted small mb-0">
            Part of order <strong><?= e((string) $slice['order_number']) ?></strong>
            · placed <?= e(date('j M Y H:i', strtotime((string) $slice['created_at']))) ?>
        </p>
    </div>
    <a href="/pharmacy/orders" class="btn btn-sm btn-light">Back to orders</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items -->
        <div class="ipl-card mb-3">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Items to prepare (<?= count($slice['items']) ?>)</h3>
                <?php foreach ($slice['items'] as $item): ?>
                    <div class="d-flex gap-2 py-2" style="border-bottom:1px dashed var(--ipl-border)">
                        <div style="width:46px;height:46px;flex:0 0 46px;border-radius:10px;background:#f2f7f5;overflow:hidden">
                            <img src="<?= e(upload_url($item['image'])) ?>" alt="" class="w-100 h-100"
                                style="object-fit:contain;padding:.25rem" loading="lazy">
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="small fw-semibold"><?= e((string) $item['product_name']) ?></div>
                            <div class="text-muted small">SKU: <?= e((string) $item['sku']) ?> · <?= (int) $item['quantity'] ?> unit(s)</div>
                        </div>
                        <span class="fw-semibold small"><?= money((float) $item['line_total']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="mt-3">
                    <div class="summary-line"><span class="text-muted">Items</span><span><?= money((float) $slice['items_subtotal']) ?></span></div>
                    <?php if ((float) $slice['tax_total'] > 0): ?>
                        <div class="summary-line"><span class="text-muted">Tax</span><span><?= money((float) $slice['tax_total']) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-line"><span class="text-muted">Delivery fee</span><span><?= money((float) $slice['delivery_fee']) ?></span></div>
                    <div class="summary-line"><span class="text-muted">Platform commission</span><span class="text-danger">−<?= money((float) $slice['platform_fee']) ?></span></div>
                    <div class="summary-line summary-total"><span>Your earnings</span><span><?= money((float) $slice['pharmacy_earnings']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Prescriptions -->
        <?php if (!empty($prescriptions)): ?>
            <div class="ipl-card mb-3" style="border-color:#f59e0b">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3">
                        <i class="bi bi-file-earmark-medical text-warning me-1"></i>
                        Prescription review
                    </h3>
                    <p class="small text-muted">
                        This order contains prescription-only medicine. Review the prescription and
                        record your decision before dispensing.
                    </p>
                    <?php foreach ($prescriptions as $rx): ?>
                        <div class="ipl-card p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div>
                                    <a href="<?= e(upload_url((string) $rx['file_path'])) ?>" target="_blank" rel="noopener"
                                        class="small fw-semibold">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>View uploaded prescription
                                    </a>
                                    <div class="text-muted small">
                                        Uploaded <?= e(date('j M Y H:i', strtotime((string) $rx['created_at']))) ?>
                                    </div>
                                </div>
                                <?= status_badge((string) $rx['status']) ?>
                            </div>

                            <?php if ($rx['status'] === 'pending'): ?>
                                <form method="post" action="/pharmacy/prescriptions/<?= (int) $rx['id'] ?>" class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="text" name="review_note" class="form-control form-control-sm"
                                        placeholder="Note (required when rejecting)">
                                    <button name="status" value="approved" class="btn btn-sm btn-success text-nowrap">
                                        Approve
                                    </button>
                                    <button name="status" value="rejected" class="btn btn-sm btn-outline-danger text-nowrap">
                                        Reject
                                    </button>
                                </form>
                            <?php elseif (!empty($rx['review_note'])): ?>
                                <div class="small text-muted fst-italic"><?= e((string) $rx['review_note']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- History -->
        <div class="ipl-card">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Activity</h3>
                <?php if (empty($history)): ?>
                    <p class="small text-muted mb-0">No activity recorded yet.</p>
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
                                <div class="when">
                                    <?= e(date('j M Y H:i', strtotime((string) $entry['created_at']))) ?>
                                    <?php if (!empty($entry['actor_name'])): ?>
                                        by <?= e((string) $entry['actor_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Customer -->
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-2">Customer</h3>
            <div class="small">
                <div class="fw-semibold"><?= e((string) ($slice['customer']['full_name'] ?? '—')) ?></div>
                <div class="text-muted"><?= e((string) ($slice['customer']['phone'] ?? '—')) ?></div>
                <div class="text-muted"><?= e((string) ($slice['customer']['email'] ?? '—')) ?></div>
            </div>

            <hr>

            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Payment</span><span><?= status_badge((string) $slice['payment_status']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Fulfilment</span>
                    <span class="text-capitalize"><?= e((string) $slice['fulfilment_method']) ?></span>
                </div>
            </div>

            <?php if (!empty($slice['customer_note'])): ?>
                <div class="alert alert-info small mt-3 mb-0">
                    <strong>Customer note:</strong> <?= e((string) $slice['customer_note']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Address -->
        <?php if (is_array($address)): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-2">
                    <i class="bi bi-geo-alt me-1"></i>
                    <?= (string) $slice['fulfilment_method'] === 'pickup' ? 'Collection' : 'Delivery address' ?>
                </h3>
                <div class="small">
                    <div class="fw-semibold"><?= e((string) ($address['recipient_name'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['address_line'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                    <div class="text-muted"><?= e((string) ($address['phone'] ?? '')) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if (!empty($actions)): ?>
            <div class="ipl-card p-3 mb-3">
                <h3 class="h6 fw-bold mb-3">Actions</h3>
                <form method="post" action="/pharmacy/orders/<?= (int) $slice['id'] ?>/action">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="note">Note to the customer (optional)</label>
                    <textarea name="note" id="note" rows="2" class="form-control mb-3"
                        placeholder="<?= in_array('cancel', $actions, true) || in_array('reject', $actions, true) ? 'Required when cancelling' : 'e.g. Ready in 20 minutes' ?>"></textarea>

                    <?php foreach ($actions as $action):
                        [$label, $btnClass, $icon] = $actionLabels[$action] ?? [ucfirst($action), 'btn-primary', 'bi-check2']; ?>
                        <button name="action" value="<?= e($action) ?>" class="btn <?= e($btnClass) ?> btn-sm w-100 mb-2">
                            <i class="bi <?= e($icon) ?> me-1"></i> <?= e($label) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="ipl-card p-3 mb-3">
                <p class="small text-muted mb-0">
                    No actions are available for this order at its current stage.
                </p>
            </div>
        <?php endif; ?>

        <!-- Delivery -->
        <?php if ((string) $slice['fulfilment_method'] === 'delivery'): ?>
            <div class="ipl-card p-3">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-truck me-1"></i>Delivery</h3>
                <?php if ($delivery !== null): ?>
                    <div class="small mb-2">
                        <?= status_badge((string) $delivery['status']) ?>
                        <?php if (!empty($delivery['personnel_name'])): ?>
                            <div class="text-muted mt-1">Rider: <?= e((string) $delivery['personnel_name']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/pharmacy/deliveries/<?= (int) ($delivery['id'] ?? 0) ?>/assign">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="personnel_id">Assign a rider</label>
                    <select name="personnel_id" id="personnel_id" class="form-select form-select-sm mb-2">
                        <option value="">Select a rider…</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= (int) $rider['id'] ?>">
                                <?= e((string) $rider['full_name']) ?>
                                <?= !empty($rider['vehicle_type']) ? '(' . e((string) $rider['vehicle_type']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-soft btn-sm w-100">Assign</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>