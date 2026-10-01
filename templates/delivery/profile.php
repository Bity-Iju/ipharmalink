<?php

/**
 * Rider profile — /delivery/profile
 *
 * @var array $record
 * @var array $user
 * @var array $stats
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$val = static fn(string $field, mixed $default = ''): string =>
(string) (\App\Session::old($field, $record[$field] ?? $user[$field] ?? $default));
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-3">Rider details</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/delivery/profile" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full name</label>
                        <input type="text" id="full_name" class="form-control" value="<?= e((string) $user['full_name']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" class="form-control" value="<?= e((string) $user['email']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required value="<?= e($val('phone')) ?>">
                        <div class="form-text">Dispatch and customers reach you on this number.</div>
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vehicle_type">Vehicle</label>
                        <select name="vehicle_type" id="vehicle_type" class="form-select">
                            <?php
                            $vehicles = ['' => 'On foot', 'motorcycle' => 'Motorcycle', 'bicycle' => 'Bicycle', 'car' => 'Car', 'van' => 'Van'];
                            foreach ($vehicles as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $val('vehicle_type') === $value ? ' selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="plate_number">Plate number</label>
                        <input type="text" name="plate_number" id="plate_number" class="form-control"
                            placeholder="ABC-123-XY" value="<?= e($val('plate_number')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="max_active_deliveries">Maximum active drops</label>
                        <input type="number" name="max_active_deliveries" id="max_active_deliveries"
                            class="form-control" min="1" max="20"
                            value="<?= e($val('max_active_deliveries', '5')) ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_available" value="1"
                                id="is_available" <?= (int) ($record['is_available'] ?? 0) === 1 ? ' checked' : '' ?>>
                            <label class="form-check-label" for="is_available">
                                <strong>Available for assignments</strong>
                                <span class="d-block small text-muted">Uncheck when you go offline.</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Your record</h2>
            <div class="small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Total deliveries</span>
                    <strong><?= (int) $stats['total'] ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Completed</span>
                    <strong><?= (int) $stats['delivered'] ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Total earned</span>
                    <strong class="text-success"><?= money((float) $stats['earned']) ?></strong>
                </div>
            </div>
        </div>

        <div class="ipl-card p-3">
            <h2 class="h6 fw-bold mb-2">Availability</h2>
            <p class="small text-muted mb-0">
                <?= (int) ($record['is_available'] ?? 0) === 1
                    ? 'You are visible to dispatch and can be assigned new drops.'
                    : 'You are currently hidden from dispatch.' ?>
            </p>
        </div>
    </div>
</div>