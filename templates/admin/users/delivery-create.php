<?php

/**
 * Create a delivery rider — /admin/delivery-personnel/create
 *
 * @var array $pharmacies
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
            <h2 class="h5 fw-bold mb-1">Add a delivery rider</h2>
            <p class="text-muted small mb-3">
                The rider receives an email with a link to set their password. They sign in from
                the same customer login page.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/delivery-personnel/create" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="full_name">Full name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required
                            value="<?= old('full_name') ?>">
                        <?= $err('full_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required
                            value="<?= old('email') ?>">
                        <?= $err('email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            placeholder="0803 000 0000" value="<?= old('phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pharmacy_id">Attached pharmacy</label>
                        <select name="pharmacy_id" id="pharmacy_id" class="form-select">
                            <option value="">Platform-wide rider</option>
                            <?php foreach ($pharmacies as $pharmacy): ?>
                                <option value="<?= (int) $pharmacy['id'] ?>" <?= old('pharmacy_id') === (string) $pharmacy['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $pharmacy['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vehicle_type">Vehicle</label>
                        <select name="vehicle_type" id="vehicle_type" class="form-select">
                            <?php foreach (
                                [
                                    '' => 'On foot',
                                    'motorcycle' => 'Motorcycle',
                                    'bicycle' => 'Bicycle',
                                    'car' => 'Car',
                                    'van' => 'Van'
                                ] as $value => $label
                            ): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="plate_number">Plate number</label>
                        <input type="text" name="plate_number" id="plate_number" class="form-control"
                            placeholder="ABC-123-XY" value="<?= old('plate_number') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">Create rider account</button>
            </form>
        </div>
    </div>
</div>