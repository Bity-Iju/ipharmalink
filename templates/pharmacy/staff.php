<?php

/**
 * Pharmacy staff management — /pharmacy/staff
 *
 * @var array $staff
 * @var array $errors
 */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Staff</h2>
    <span class="text-muted small"><?= count($staff) ?> member<?= count($staff) === 1 ? '' : 's' ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if (empty($staff)): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-person-badge"></i></div>
                <h3 class="h5 fw-bold">No staff added</h3>
                <p class="mb-0">Add staff so they can help process orders and manage inventory.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($staff as $member): ?>
                    <div class="ipl-card p-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="brand-mark" style="width:40px;height:40px;font-size:1rem">
                                    <i class="bi bi-person"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold"><?= e((string) $member['full_name']) ?></div>
                                    <div class="text-muted small">
                                        <?= e((string) $member['email']) ?>
                                        <?php if (!empty($member['job_title'])): ?>
                                            · <?= e((string) $member['job_title']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?= status_badge((string) $member['status']) ?>
                        </div>

                        <form method="post" action="/pharmacy/staff/<?= (int) $member['id'] ?>">
                            <?= csrf_field() ?>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Job title</label>
                                    <input type="text" name="job_title" class="form-control form-control-sm"
                                        value="<?= e((string) ($member['job_title'] ?? '')) ?>"
                                        placeholder="e.g. Pharmacist">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="active" <?= $member['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                                        <option value="suspended" <?= $member['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <?php
                                $perms = [
                                    'can_manage_orders'    => 'Process orders',
                                    'can_manage_inventory' => 'Manage inventory',
                                    'can_manage_products'  => 'Manage products',
                                    'can_view_reports'     => 'View reports',
                                ];
                                foreach ($perms as $key => $label): ?>
                                    <div class="col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?= e($key) ?>"
                                                value="1" id="<?= e($key) . '_' . (int) $member['id'] ?>"
                                                <?= (int) $member[$key] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label small"
                                                for="<?= e($key) . '_' . (int) $member['id'] ?>"><?= e($label) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary">Save</button>
                            </div>
                        </form>

                        <form method="post" action="/pharmacy/staff/<?= (int) $member['id'] ?>/delete" class="mt-2"
                            data-confirm="Remove this person from your pharmacy?">
                            <?= csrf_field() ?>
                            <button class="btn btn-link btn-sm text-danger p-0">Remove from pharmacy</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h3 class="h6 fw-bold mb-3">Add a staff member</h3>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/pharmacy/staff" novalidate>
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label required" for="full_name">Full name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required
                        value="<?= old('full_name') ?>">
                    <?= $err('full_name') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label required" for="email">Email address</label>
                    <input type="email" name="email" id="email" class="form-control" required
                        value="<?= old('email') ?>">
                    <div class="form-text">If they already have an account we will attach it.</div>
                    <?= $err('email') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="phone">Phone</label>
                    <input type="tel" name="phone" id="phone" class="form-control" value="<?= old('phone') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="job_title">Job title</label>
                    <input type="text" name="job_title" id="job_title" class="form-control"
                        placeholder="e.g. Pharmacy Technician" value="<?= old('job_title') ?>">
                </div>

                <label class="form-label">Permissions</label>
                <?php
                $perms = [
                    'can_manage_orders'    => 'Process orders',
                    'can_manage_inventory' => 'Manage inventory',
                    'can_manage_products'  => 'Manage products',
                    'can_view_reports'     => 'View reports',
                ];
                foreach ($perms as $key => $label): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="1"
                            id="new_<?= e($key) ?>" <?= $key === 'can_manage_orders' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="new_<?= e($key) ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary w-100 mt-3">Add staff member</button>
            </form>
        </div>
    </div>
</div>