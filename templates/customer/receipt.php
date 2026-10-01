<?php

/**
 * Printable order receipt — /account/orders/{id}/receipt
 *
 * @var array $order
 * @var array $user
 * @var array $business
 */
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt <?= e((string) $order['order_number']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>

<body style="background:#fff">
    <div class="container py-4" style="max-width:760px">

        <div class="d-flex justify-content-between align-items-start mb-4 no-print">
            <a href="/account/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to order
            </a>
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
        </div>

        <div class="d-flex justify-content-between mb-4">
            <div>
                <h1 class="h5 fw-bold mb-1"><?= e((string) $business['name']) ?></h1>
                <div class="small text-muted">
                    <?php if (!empty($business['address'])): ?><?= e((string) $business['address']) ?><br><?php endif; ?>
                <?php if (!empty($business['phone'])): ?>Tel: <?= e((string) $business['phone']) ?><br><?php endif; ?>
            <?php if (!empty($business['email'])): ?><?= e((string) $business['email']) ?><?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <h2 class="h6 fw-bold mb-1">Receipt</h2>
                <div class="small text-muted">
                    <div><?= e((string) $order['order_number']) ?></div>
                    <div><?= e(date('j M Y H:i', strtotime((string) $order['created_at']))) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="small text-muted text-uppercase mb-1">Billed to</div>
                <div class="small">
                    <div class="fw-semibold"><?= e((string) ($user['full_name'] ?? '')) ?></div>
                    <div><?= e((string) ($user['email'] ?? '')) ?></div>
                    <div><?= e((string) ($user['phone'] ?? '')) ?></div>
                </div>
            </div>
            <div class="col-6">
                <div class="small text-muted text-uppercase mb-1">
                    <?= (string) $order['fulfilment_method'] === 'pickup' ? 'Collection' : 'Delivery' ?>
                </div>
                <div class="small">
                    <?php
                    $address = $order['address_snapshot'] !== null
                        ? json_decode((string) $order['address_snapshot'], true)
                        : null;
                    if (is_array($address)): ?>
                        <div><?= e((string) ($address['address_line'] ?? '')) ?></div>
                        <div><?= e((string) ($address['city'] ?? '')) ?>, <?= e((string) ($address['state'] ?? '')) ?></div>
                    <?php else: ?>
                        <div class="text-muted">Collected at the pharmacy</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <table class="table ipl-table mb-3">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Pharmacy</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Unit</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item):
                    $pharmacyName = '';
                    foreach ($order['slices'] as $slice) {
                        if ((int) $slice['pharmacy_id'] === (int) $item['pharmacy_id']) {
                            $pharmacyName = (string) $slice['pharmacy_name'];
                            break;
                        }
                    } ?>
                    <tr>
                        <td>
                            <?= e((string) $item['product_name']) ?>
                            <div class="text-muted small">SKU: <?= e((string) $item['sku']) ?></div>
                        </td>
                        <td class="small"><?= e($pharmacyName) ?></td>
                        <td class="text-end"><?= (int) $item['quantity'] ?></td>
                        <td class="text-end"><?= money((float) $item['unit_price']) ?></td>
                        <td class="text-end"><?= money((float) $item['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row justify-content-end">
            <div class="col-md-6">
                <div class="summary-line"><span class="text-muted">Subtotal</span><span><?= money((float) $order['subtotal']) ?></span></div>
                <?php if ((float) $order['tax_total'] > 0): ?>
                    <div class="summary-line"><span class="text-muted">Tax</span><span><?= money((float) $order['tax_total']) ?></span></div>
                <?php endif; ?>
                <div class="summary-line"><span class="text-muted">Delivery</span><span><?= money((float) $order['delivery_fee']) ?></span></div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="summary-line text-success"><span>Discount</span><span>−<?= money((float) $order['discount_total']) ?></span></div>
                <?php endif; ?>
                <div class="summary-line summary-total"><span>Total</span><span><?= money((float) $order['total']) ?></span></div>
                <div class="small text-muted text-end mt-1">
                    Paid: <?= status_badge((string) $order['payment_status']) ?>
                </div>
            </div>
        </div>

        <p class="text-center text-muted small mt-5 mb-0">
            Thank you for shopping with <?= e((string) $business['name']) ?>.
            Medicines are dispensed by licensed pharmacies only.
        </p>
    </div>
</body>

</html>