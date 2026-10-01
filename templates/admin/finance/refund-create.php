<?php

/**
 * New refund — /admin/refunds/create
 *
 * @var \App\Paginator $paginator  paid orders eligible for refund
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-1">Issue a refund</h2>
            <p class="text-muted small mb-3">
                Refunding marks the order as refunded and reverses the pharmacy's commission.
                The customer is notified.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/refunds/create" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label required" for="order_id">Order</label>
                    <select name="order_id" id="order_id" class="form-select" required>
                        <option value="">Select a paid order…</option>
                        <?php foreach ($paginator->items() as $order): ?>
                            <option value="<?= (int) $order['id'] ?>" <?= old('order_id') === (string) $order['id'] ? ' selected' : '' ?>>
                                <?= e((string) $order['order_number']) ?>
                                — <?= e((string) $order['customer_name']) ?>
                                (<?= money((float) $order['total']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('order_id') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="amount">Amount to refund (₦)</label>
                    <input type="number" name="amount" id="amount" class="form-control" required
                        min="1" step="0.01" value="<?= old('amount') ?>">
                    <div class="form-text">Must not exceed the order total.</div>
                    <?= $err('amount') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="reason">Reason</label>
                    <textarea name="reason" id="reason" rows="2" class="form-control" required
                        placeholder="e.g. Customer received damaged packaging"><?= old('reason') ?></textarea>
                    <?= $err('reason') ?>
                </div>
                <button type="submit" class="btn btn-primary" data-confirm="Issue this refund?">
                    Issue refund
                </button>
                <a href="/admin/refunds" class="btn btn-light">Cancel</a>
            </form>
        </div>
    </div>
</div>