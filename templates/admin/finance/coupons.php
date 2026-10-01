<?php

/**
 * Admin coupons — /admin/coupons
 *
 * @var array $coupons
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="row g-4">
    <div class="col-lg-8">
        <h2 class="h5 fw-bold mb-3">Coupons <span class="text-muted fw-normal">(<?= count($coupons) ?>)</span></h2>

        <?php if ($coupons === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-tag"></i></div>
                <h3 class="h5 fw-bold">No coupons yet</h3>
                <p class="mb-0">Create a coupon to run a promotion.</p>
            </div>
        <?php else: ?>
            <div class="ipl-card">
                <div class="table-responsive">
                    <table class="table ipl-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th class="text-end">Value</th>
                                <th class="text-end">Min order</th>
                                <th class="text-end">Used</th>
                                <th>Status</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr>
                                    <td>
                                        <form method="post" action="/admin/coupons/<?= (int) $coupon['id'] ?>"
                                            class="d-flex gap-1 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="text" name="description" value="<?= e((string) ($coupon['description'] ?? '')) ?>"
                                                class="form-control form-control-sm border-0 p-0" style="width:150px"
                                                placeholder="Description" aria-label="Description">
                                        </form>
                                        <span class="chip mt-1"><?= e((string) $coupon['code']) ?></span>
                                    </td>
                                    <td class="small"><?= ucfirst((string) $coupon['type']) ?></td>
                                    <td class="text-end fw-semibold">
                                        <?= $coupon['type'] === 'percent' ? (float) $coupon['value'] . '%' : money((float) $coupon['value']) ?>
                                    </td>
                                    <td class="text-end small"><?= money((float) $coupon['min_order_value']) ?></td>
                                    <td class="text-end small">
                                        <?= (int) $coupon['usage_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . (int) $coupon['usage_limit'] : '' ?>
                                    </td>
                                    <td><?= status_badge((int) $coupon['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                                    <td class="text-end">
                                        <form method="post" action="/admin/coupons/<?= (int) $coupon['id'] ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="is_active" value="<?= (int) $coupon['is_active'] === 1 ? '0' : '1' ?>">
                                            <button class="btn btn-sm btn-light">
                                                <i class="bi bi-<?= (int) $coupon['is_active'] === 1 ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Create a coupon</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/coupons" novalidate>
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label required" for="code">Code</label>
                    <input type="text" name="code" id="code" class="form-control" required
                        placeholder="WELCOME10" value="<?= old('code') ?>">
                    <?= $err('code') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="description">Description</label>
                    <input type="text" name="description" id="description" class="form-control"
                        placeholder="10% off first order" value="<?= old('description') ?>">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="type">Type</label>
                        <select name="type" id="type" class="form-select">
                            <option value="percent">Percent</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="value">Value</label>
                        <input type="number" name="value" id="value" class="form-control" required
                            min="0" step="0.01" value="<?= old('value', '10') ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label" for="min_order_value">Min order (₦)</label>
                        <input type="number" name="min_order_value" id="min_order_value" class="form-control"
                            min="0" step="0.01" value="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="max_discount">Max discount (₦)</label>
                        <input type="number" name="max_discount" id="max_discount" class="form-control"
                            min="0" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="usage_limit">Usage limit</label>
                        <input type="number" name="usage_limit" id="usage_limit" class="form-control" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="per_user_limit">Per customer</label>
                        <input type="number" name="per_user_limit" id="per_user_limit" class="form-control"
                            min="1" value="3">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="expires_at">Expires at</label>
                        <input type="datetime-local" name="expires_at" id="expires_at" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">Create coupon</button>
            </form>
        </div>
    </div>
</div>