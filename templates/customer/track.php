<?php

/**
 * Order tracking — /account/orders/{id}/track
 *
 * @var array $order
 */
$steps = [
    'pending_payment'    => 'Payment pending',
    'paid'               => 'Payment received',
    'received'           => 'Pharmacy notified',
    'processing'         => 'Processing',
    'preparing'          => 'Being prepared',
    'ready_for_delivery' => 'Ready for delivery',
    'out_for_delivery'   => 'Out for delivery',
    'delivered'          => 'Delivered',
];
$status  = (string) $order['status'];
$current = array_search($status, array_keys($steps), true);
$isDone  = in_array($status, ['delivered', 'cancelled', 'refunded'], true);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Tracking <?= e((string) $order['order_number']) ?></h2>
    <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-light">Order details</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="ipl-card p-4">
            <div class="timeline">
                <?php $index = 0;
                foreach ($steps as $key => $label):
                    $reached = $current !== false && $index <= $current;
                    $isCurrent = $key === $status;
                    $entry = null;
                    foreach ($order['history'] as $h) {
                        if (($h['scope'] ?? '') === 'order' && (string) $h['to_status'] === $key) {
                            $entry = $h;
                        }
                    } ?>
                    <div class="timeline-item<?= $reached || $isDone ? ' is-done' : '' ?>">
                        <div class="d-flex justify-content-between">
                            <strong class="<?= $reached ? '' : 'text-muted' ?>"><?= e($label) ?></strong>
                            <?php if ($entry !== null): ?>
                                <span class="when"><?= e(date('j M Y H:i', strtotime((string) $entry['created_at']))) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isCurrent): ?>
                            <span class="badge bg-info mt-1">Current status</span>
                        <?php endif; ?>
                    </div>
                <?php $index++;
                endforeach; ?>
            </div>

            <?php if (in_array($status, ['cancelled', 'refunded'], true)): ?>
                <div class="alert alert-<?= $status === 'cancelled' ? 'warning' : 'info' ?> mt-3 mb-0">
                    This order was <?= e($status) ?>.
                    <?php if (!empty($order['cancel_reason'])): ?>
                        Reason: <?= e((string) $order['cancel_reason']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php foreach ($order['slices'] as $slice): ?>
            <div class="ipl-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <a href="/pharmacy/<?= e((string) $slice['pharmacy_slug']) ?>" class="fw-semibold small text-reset">
                        <?= e((string) $slice['pharmacy_name']) ?>
                    </a>
                    <?= status_badge((string) $slice['status']) ?>
                </div>
                <div class="small text-muted">
                    <div><?= e((string) $slice['sub_order_number']) ?></div>
                    <?php if (!empty($slice['tracking_number'])): ?>
                        <div class="mt-1">Tracking: <?= e((string) $slice['tracking_number']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($slice['delivery_status'])): ?>
                        <div class="mt-1">Delivery: <?= e(ucfirst(str_replace('_', ' ', (string) $slice['delivery_status']))) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>