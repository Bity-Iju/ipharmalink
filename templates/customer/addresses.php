<?php

/**
 * Customer address book — /account/addresses
 *
 * @var array $addresses
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
$editing = false;
?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php if (empty($addresses)): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-geo-alt"></i></div>
                <h2 class="h5 fw-bold">No delivery addresses yet</h2>
                <p class="mb-3">Add an address so we know where to deliver your order.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($addresses as $address): ?>
                    <div class="col-md-6">
                        <div class="ipl-card p-3 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><?= e((string) $address['label']) ?></strong>
                                <?php if ((int) $address['is_default'] === 1): ?>
                                    <span class="badge bg-success">Default</span>
                                <?php endif; ?>
                            </div>

                            <div class="small text-muted flex-grow-1">
                                <div class="fw-semibold text-dark"><?= e((string) $address['recipient_name']) ?></div>
                                <?= e((string) $address['address_line']) ?><br>
                                <?= e((string) $address['city']) ?>, <?= e((string) $address['state']) ?>
                                <?php if (!empty($address['landmark'])): ?>
                                    <br><span class="fst-italic">Near <?= e((string) $address['landmark']) ?></span>
                                <?php endif; ?>
                                <div class="mt-1"><i class="bi bi-telephone me-1"></i><?= e((string) $address['phone']) ?></div>
                                <?php if ((int) $address['order_count'] > 0): ?>
                                    <div class="mt-1">Used on <?= (int) $address['order_count'] ?> order(s)</div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <?php if ((int) $address['is_default'] !== 1): ?>
                                    <form method="post" action="/account/addresses/<?= (int) $address['id'] ?>/default" class="m-0">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-light">Make default</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/account/addresses/<?= (int) $address['id'] ?>/delete" class="m-0"
                                    data-confirm="Delete this address?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-link text-danger text-decoration-none">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Add a delivery address</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/account/addresses" novalidate>
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label required" for="label">Label</label>
                        <input type="text" name="label" id="label" class="form-control" required
                            placeholder="Home, Office…" value="<?= old('label', 'Home') ?>">
                        <?= $err('label') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="recipient_name">Recipient name</label>
                        <input type="text" name="recipient_name" id="recipient_name" class="form-control" required
                            value="<?= old('recipient_name') ?>">
                        <?= $err('recipient_name') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required
                            placeholder="0803 000 0000" value="<?= old('phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="state">State</label>
                        <input type="text" name="state" id="state" class="form-control" required
                            value="<?= old('state') ?>">
                        <?= $err('state') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="city">City / town</label>
                        <input type="text" name="city" id="city" class="form-control" required
                            value="<?= old('city') ?>">
                        <?= $err('city') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="address_line">Street address</label>
                        <input type="text" name="address_line" id="address_line" class="form-control" required
                            value="<?= old('address_line') ?>">
                        <?= $err('address_line') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="landmark">Landmark (optional)</label>
                        <input type="text" name="landmark" id="landmark" class="form-control"
                            placeholder="Opposite the bank" value="<?= old('landmark') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="latitude">Latitude</label>
                        <input type="text" name="latitude" id="latitude" class="form-control" value="<?= old('latitude') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="longitude">Longitude</label>
                        <input type="text" name="longitude" id="longitude" class="form-control" value="<?= old('longitude') ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default">
                            <label class="form-check-label small" for="is_default">Make this my default address</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">Save address</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>