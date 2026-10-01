<?php

/**
 * Admin categories — /admin/categories
 *
 * @var array $categories
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
        <h2 class="h5 fw-bold mb-3">Product categories</h2>

        <?php if ($categories === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-tags"></i></div>
                <h3 class="h5 fw-bold">No categories yet</h3>
                <p class="mb-0">Add your first category to organise the catalogue.</p>
            </div>
        <?php else: ?>
            <div class="ipl-card">
                <div class="table-responsive">
                    <table class="table ipl-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Slug</th>
                                <th class="text-end">Products</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="brand-mark" style="width:32px;height:32px;font-size:.85rem">
                                                <i class="bi <?= e((string) ($category['icon'] ?: 'bi-tags')) ?>"></i>
                                            </span>
                                            <div>
                                                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>"
                                                    class="d-flex gap-1 align-items-center">
                                                    <?= csrf_field() ?>
                                                    <input type="text" name="name" value="<?= e((string) $category['name']) ?>"
                                                        class="form-control form-control-sm border-0 p-0 fw-semibold"
                                                        style="width:150px" aria-label="Category name">
                                                    <button class="btn btn-sm btn-link p-0" title="Save name">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                </form>
                                                <span class="text-muted small"><?= e((string) $category['slug']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?= e((string) $category['slug']) ?></td>
                                    <td class="text-end"><?= (int) $category['product_count'] ?></td>
                                    <td><?= status_badge((int) $category['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="is_active" value="<?= (int) $category['is_active'] === 1 ? '0' : '1' ?>">
                                                <button class="btn btn-sm btn-light" title="Toggle active">
                                                    <i class="bi bi-<?= (int) $category['is_active'] === 1 ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </form>
                                            <a href="/category/<?= e((string) $category['slug']) ?>" target="_blank" rel="noopener"
                                                class="btn btn-sm btn-light" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete"
                                                class="m-0" data-confirm="Delete this category?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-light text-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <?php foreach ($category['children'] as $child): ?>
                                    <tr style="background:#fafcfb">
                                        <td style="padding-left:3rem">
                                            <form method="post" action="/admin/categories/<?= (int) $child['id'] ?>"
                                                class="d-flex gap-1 align-items-center">
                                                <?= csrf_field() ?>
                                                <input type="text" name="name" value="<?= e((string) $child['name']) ?>"
                                                    class="form-control form-control-sm border-0 p-0" style="width:150px"
                                                    aria-label="Subcategory name">
                                                <button class="btn btn-sm btn-link p-0" title="Save name">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            </form>
                                            <span class="text-muted" style="font-size:.72rem">
                                                <i class="bi bi-arrow-return-right me-1"></i><?= e((string) $child['slug']) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= e((string) $child['slug']) ?></td>
                                        <td class="text-end small"><?= (int) $child['product_count'] ?></td>
                                        <td><?= status_badge((int) $child['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                                        <td class="text-end">
                                            <form method="post" action="/admin/categories/<?= (int) $child['id'] ?>/delete"
                                                class="m-0 d-inline" data-confirm="Delete this subcategory?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Add a category</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/admin/categories" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label required" for="name">Name</label>
                    <input type="text" name="name" id="name" class="form-control" required
                        placeholder="e.g. Pain Relief" value="<?= old('name') ?>">
                    <?= $err('name') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="parent_id">Parent category</label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">Top level</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= old('parent_id') === (string) $category['id'] ? ' selected' : '' ?>>
                                <?= e((string) $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Leave blank for a top-level category.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="icon">Icon</label>
                    <input type="text" name="icon" id="icon" class="form-control"
                        placeholder="bi-capsule-pill" value="<?= old('icon') ?>">
                    <div class="form-text">A Bootstrap Icons name, e.g. <code>bi-capsule-pill</code>.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="description" rows="2" class="form-control"><?= old('description') ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="image">Image</label>
                    <input type="file" name="image" id="image" class="form-control" accept="image/*">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Add category</button>
            </form>
        </div>
    </div>
</div>