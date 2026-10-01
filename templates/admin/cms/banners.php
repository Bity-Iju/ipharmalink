<?php

/**
 * Admin banners — /admin/banners
 *
 * @var array $banners
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
        <h2 class="h5 fw-bold mb-3">Banners <span class="text-muted fw-normal">(<?= count($banners) ?>)</span></h2>

        <?php if ($banners === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-images"></i></div>
                <h3 class="h5 fw-bold">No banners</h3>
                <p class="mb-0">Add a hero banner to promote a campaign on the homepage.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($banners as $banner): ?>
                    <div class="ipl-card p-3 d-flex flex-wrap gap-3 align-items-center">
                        <?php if (!empty($banner['image'])): ?>
                            <img src="<?= e(upload_url((string) $banner['image'])) ?>" alt=""
                                width="120" height="70" class="rounded" style="object-fit:cover">
                        <?php else: ?>
                            <div class="rounded" style="width:120px;height:70px;background:#e3ebe8"></div>
                        <?php endif; ?>

                        <div class="flex-grow-1 min-w-0">
                            <form method="post" action="/admin/banners/<?= (int) $banner['id'] ?>">
                                <?= csrf_field() ?>
                                <input type="text" name="title" value="<?= e((string) $banner['title']) ?>"
                                    class="form-control form-control-sm fw-semibold mb-1" aria-label="Banner title">
                                <input type="text" name="subtitle" value="<?= e((string) ($banner['subtitle'] ?? '')) ?>"
                                    class="form-control form-control-sm mb-1" placeholder="Subtitle" aria-label="Subtitle">
                                <div class="d-flex flex-wrap gap-1">
                                    <select name="placement" class="form-select form-select-sm" style="width:auto">
                                        <?php foreach (
                                            [
                                                'home_slider' => 'Home slider',
                                                'home_mid' => 'Homepage mid',
                                                'pharmacy_top' => 'Pharmacy top'
                                            ] as $value => $label
                                        ): ?>
                                            <option value="<?= e($value) ?>" <?= $banner['placement'] === $value ? ' selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="is_active" value="<?= (int) $banner['is_active'] === 1 ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>
                            <div class="mt-1">
                                <?= status_badge((int) $banner['is_active'] === 1 ? 'active' : 'inactive') ?>
                                <span class="text-muted small">#<?= (int) $banner['sort_order'] ?></span>
                            </div>
                        </div>

                        <form method="post" action="/admin/banners/<?= (int) $banner['id'] ?>/delete" class="m-0"
                            data-confirm="Delete this banner?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Add a banner</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/banners" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label required" for="title">Title</label>
                    <input type="text" name="title" id="title" class="form-control" required
                        value="<?= old('title') ?>">
                    <?= $err('title') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="subtitle">Subtitle</label>
                    <textarea name="subtitle" id="subtitle" rows="2" class="form-control"><?= old('subtitle') ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="button_text">Button text</label>
                    <input type="text" name="button_text" id="button_text" class="form-control"
                        placeholder="Shop now" value="<?= old('button_text') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="button_link">Button link</label>
                    <input type="text" name="button_link" id="button_link" class="form-control"
                        placeholder="/products" value="<?= old('button_link') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="placement">Placement</label>
                    <select name="placement" id="placement" class="form-select">
                        <?php foreach (
                            [
                                'home_slider' => 'Home slider',
                                'home_mid' => 'Homepage mid',
                                'pharmacy_top' => 'Pharmacy top'
                            ] as $value => $label
                        ): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="image">Image</label>
                    <input type="file" name="image" id="image" class="form-control" accept="image/*">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" value="0">
                </div>
                <button type="submit" class="btn btn-primary w-100">Add banner</button>
            </form>
        </div>
    </div>
</div>