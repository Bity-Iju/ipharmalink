<?php

/**
 * Rider dashboard — /delivery/dashboard
 *
 * @var array $stats
 * @var float $earnings
 * @var array $deliveries
 * @var array|null $profile
 */
$statusHint = [
    'assigned'   => ['Collect this order from the pharmacy', 'btn-primary', 'bi-box-arrow-up'],
    'picked_up'  => ['Mark as in transit when you set off', 'btn-primary', 'bi-truck'],
    'in_transit' => ['Mark delivered when you hand it over', 'btn-success', 'bi-check2-circle'],
];
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-list-task',
            'label' => 'Active drops',
            'value' => (int) $stats['assigned'],
            'tone' => 'warning',
            'link' => '/delivery/orders',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-truck',
            'label' => 'In transit',
            'value' => (int) $stats['in_transit'],
            'tone' => 'info',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-check2-circle',
            'label' => 'Delivered',
            'value' => (int) $stats['delivered'],
            'tone' => 'success',
            'link' => '/delivery/history',
        ]); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php \App\View::include('components/stat-tile', [
            'icon' => 'bi-cash-coin',
            'label' => "Today's earnings",
            'value' => money($earnings),
            'tone' => 'primary',
        ]); ?>
    </div>
</div>

<?php if ($profile !== null && (int) $profile['is_available'] === 0): ?>
    <div class="alert alert-warning">
        <strong>You are marked unavailable.</strong>
        Dispatch will not assign new drops to you.
        <a href="/delivery/profile" class="btn btn-sm btn-light ms-2">Change availability</a>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Active deliveries</h2>
    <a href="/delivery/orders" class="btn btn-sm btn-light">All deliveries</a>
</div>

<?php if (empty($deliveries)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-bicycle"></i></div>
        <h3 class="h5 fw-bold">Nothing assigned right now</h3>
        <p class="mb-0">When dispatch assigns you a drop it will appear here straight away.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($deliveries as $delivery):
            $address = $delivery['address_snapshot'] !== null
                ? json_decode((string) $delivery['address_snapshot'], true) : null;
            $hint = $statusHint[(string) $delivery['status']] ?? ['Open this delivery', 'btn-primary', 'bi-box-arrow-up']; ?>
            <div class="col-md-6 col-xxl-4">
                <div class="ipl-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold"><?= e((string) $delivery['sub_order_number']) ?></div>
                            <div class="text-muted small"><?= e((string) $delivery['pharmacy_name']) ?></div>
                        </div>
                        <?= status_badge((string) $delivery['status']) ?>
                    </div>

                    <div class="small flex-grow-1">
                        <div class="mb-2">
                            <span class="text-muted">Customer</span>
                            <div class="fw-semibold"><?= e((string) $delivery['customer_name']) ?></div>
                            <a href="tel:<?= e((string) $delivery['customer_phone']) ?>" class="small">
                                <i class="bi bi-telephone me-1"></i><?= e((string) $delivery['customer_phone']) ?>
                            </a>
                        </div>

                        <div class="mb-2">
                            <span class="text-muted">Pick up from</span>
                            <div><?= e((string) $delivery['pharmacy_address']) ?></div>
                            <a href="tel:<?= e((string) $delivery['pharmacy_phone']) ?>" class="small">
                                <i class="bi bi-telephone me-1"></i><?= e((string) $delivery['pharmacy_phone']) ?>
                            </a>
                        </div>

                        <div>
                            <span class="text-muted">Deliver to</span>
                            <?php if (is_array($address)): ?>
                                <div><?= e((string) ($address['address_line'] ?? '')) ?></div>
                                <div><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                            <?php else: ?>
                                <div class="text-muted">See order details</div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($delivery['customer_note'])): ?>
                            <div class="alert alert-info py-1 px-2 mt-2 small mb-0">
                                <strong>Note:</strong> <?= e((string) $delivery['customer_note']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <a href="https://maps.google.com/?q=<?= urlencode((string) ($address['latitude'] ?? '') . ',' . (string) ($address['longitude'] ?? '')) ?>"
                            target="_blank" rel="noopener" class="btn btn-sm btn-light">
                            <i class="bi bi-map me-1"></i>Map
                        </a>
                        <a href="/delivery/orders/<?= (int) $delivery['id'] ?>"
                            class="btn btn-sm <?= e($hint[1]) ?> flex-grow-1">
                            <i class="bi <?= e($hint[2]) ?> me-1"></i> <?= e($hint[0]) ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>