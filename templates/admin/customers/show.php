<?php

/**
 * Admin customer detail — /admin/customers/{id}
 *
 * @var array $customer
 * @var array $orders
 * @var array $addresses
 * @var array $logs
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <span class="brand-mark" style="width:44px;height:44px;font-size:1.1rem">
            <i class="bi bi-person"></i>
        </span>
        <div>
            <h2 class="h5 fw-bold mb-0"><?= e((string) $customer['full_name']) ?></h2>
            <div class="text-muted small">
                <?= e((string) $customer['email']) ?> · <?= status_badge((string) $customer['status']) ?>
            </div>
        </div>
    </div>
    <form method="post" action="/admin/customers/<?= (int) $customer['id'] ?>/status" class="d-flex gap-2 m-0">
        <?= csrf_field() ?>
        <select name="status" class="form-select form-select-sm" style="width:auto">
            <?php foreach (['active', 'pending', 'suspended', 'deactivated'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $customer['status'] === $option ? ' selected' : '' ?>>
                    <?= e(ucfirst($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary">Update</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <?php
    $totalSpent = array_sum(array_map('floatval', array_column($orders, 'total')));
    $tiles = [
        ['Orders', count($orders), 'bi-receipt', 'primary', ''],
        ['Total spent', money($totalSpent), 'bi-currency-naira', 'success', ''],
        ['Addresses', count($addresses), 'bi-geo-alt', 'info', ''],
        ['Joined', date('j M Y', strtotime((string) $customer['created_at'])), 'bi-calendar', 'secondary', ''],
    ];
    foreach ($tiles as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-xl-3">
            <?php \App\View::include('components/stat-tile', [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'tone' => $tone,
                'link' => $link ?: null,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="ipl-card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Order history</h2>
                <?php if ($orders === []): ?>
                    <p class="small text-muted mb-0">This customer has not placed any orders.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="small">
                                            <a href="/admin/orders/<?= (int) $order['id'] ?>" class="fw-semibold">
                                                <?= e((string) $order['order_number']) ?>
                                            </a>
                                        </td>
                                        <td class="small text-muted"><?= e(date('j M Y', strtotime((string) $order['created_at']))) ?></td>
                                        <td class="text-end small fw-semibold"><?= money((float) $order['total']) ?></td>
                                        <td><?= status_badge((string) $order['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ipl-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Account activity</h2>
                <?php if ($logs === []): ?>
                    <p class="small text-muted mb-0">No recorded activity.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach (array_slice($logs, 0, 15) as $log): ?>
                            <div class="timeline-item is-done">
                                <div class="small fw-semibold"><?= e((string) $log['action']) ?></div>
                                <div class="text-muted small"><?= e(str_excerpt((string) ($log['description'] ?? ''), 80)) ?></div>
                                <div class="when"><?= e(date('j M Y H:i', strtotime((string) $log['created_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Contact</h2>
            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Email</span>
                    <span class="text-truncate ms-2"><?= e((string) $customer['email']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Phone</span><span><?= e((string) ($customer['phone'] ?? '—')) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Verified</span>
                    <span><?= $customer['email_verified_at'] !== null ? 'Yes' : 'No' ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Last login</span>
                    <span><?= $customer['last_login_at'] !== null ? e(date('j M Y H:i', strtotime((string) $customer['last_login_at']))) : 'Never' ?></span>
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h2 class="h6 fw-bold mb-2">Saved addresses</h2>
            <?php if ($addresses === []): ?>
                <p class="small text-muted mb-0">No saved addresses.</p>
            <?php else: ?>
                <div class="d-grid gap-2">
                    <?php foreach ($addresses as $address): ?>
                        <div class="small p-2 rounded" style="background:#f6f9f8">
                            <div class="fw-semibold">
                                <?= e((string) $address['label']) ?>
                                <?php if ((int) $address['is_default'] === 1): ?>
                                    <span class="badge bg-success ms-1">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted">
                                <?= e((string) $address['address_line']) ?>,
                                <?= e((string) $address['city']) ?>, <?= e((string) $address['state']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>