<?php

/**
 * Admin brands — /admin/brands
 *
 * @var array $brands
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
        <h2 class="h5 fw-bold mb-1">Brands</h2>
        <p class="text-muted small mb-3"><?= count($brands) ?> brand(s) on the platform.</p>

        <?php if ($brands === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-award"></i></div>
                <h3 class="h5 fw-bold">No brands yet</h3>
                <p class="mb-0">Add brands so pharmacies can file products under them.</p>
            </div>
        <?php else: ?>
            <div class="ipl-card">
                <div class="table-responsive">
                    <table class="table ipl-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Slug</th>
                                <th class="text-end">Products</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $brand): ?>
                                <tr>
                                    <td>
                                        <form method="post" action="/admin/brands/<?= (int) $brand['id'] ?>"
                                            class="d-flex gap-1 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="text" name="name" value="<?= e((string) $brand['name']) ?>"
                                                class="form-control form-control-sm border-0 p-0 fw-semibold"
                                                style="width:170px" aria-label="Brand name">
                                            <button class="btn btn-sm btn-link p-0" title="Save name">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="small text-muted"><?= e((string) $brand['slug']) ?></td>
                                    <td class="text-end"><?= (int) $brand['product_count'] ?></td>
                                    <td><?= status_badge((int) $brand['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="post" action="/admin/brands/<?= (int) $brand['id'] ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="is_active" value="<?= (int) $brand['is_active'] === 1 ? '0' : '1' ?>">
                                                <button class="btn btn-sm btn-light" title="Toggle active">
                                                    <i class="bi bi-<?= (int) $brand['is_active'] === 1 ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/brands/<?= (int) $brand['id'] ?>/delete"
                                                class="m-0" data-confirm="Delete this brand?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
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
            <h2 class="h6 fw-bold mb-3">Add a brand</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/brands" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label required" for="name">Brand name</label>
                    <input type="text" name="name" id="name" class="form-control" required
                        placeholder="e.g. Emzor" value="<?= old('name') ?>">
                    <?= $err('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="logo">Logo</label>
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                </div>
                <button type="submit" class="btn btn-primary w-100">Add brand</button>
            </form>
        </div>
    </div>
</div>