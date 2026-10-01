<?php

/**
 * Admin page editor — /admin/pages/create and /admin/pages/{id}
 *
 * @var array|null $page
 * @var array      $errors
 */
$isEdit = is_array($page);
$action = $isEdit ? '/admin/pages/' . (int) $page['id'] : '/admin/pages/create';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0"><?= $isEdit ? 'Edit page' : 'New page' ?></h2>
    <a href="/admin/pages" class="btn btn-sm btn-light">Back to pages</a>
</div>

<form method="post" action="<?= e($action) ?>" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="ipl-card p-3">
            <div class="mb-3">
                <label class="form-label required" for="title">Title</label>
                <input type="text" name="title" id="title" class="form-control" required
                    maxlength="200" value="<?= e((string) old('title', $isEdit ? $page['title'] : '')) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="slug">Slug</label>
                <input type="text" name="slug" id="slug" class="form-control" maxlength="200"
                    placeholder="leave blank to generate from the title"
                    value="<?= e((string) old('slug', $isEdit ? $page['slug'] : '')) ?>">
            </div>

            <div class="mb-0">
                <label class="form-label" for="body">Content</label>
                <textarea name="body" id="body" class="form-control" rows="18"><?= e((string) old('body', $isEdit ? $page['body'] : '')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Publishing</h3>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="is_published" id="is_published"
                    value="1" <?= old('is_published', $isEdit ? $page['is_published'] : 1) ? ' checked' : '' ?>>
                <label class="form-check-label" for="is_published">Published</label>
            </div>

            <p class="text-muted small mb-0">
                Unpublished pages are hidden from the storefront but stay editable.
            </p>
        </div>

        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Search engine</h3>

            <div class="mb-3">
                <label class="form-label" for="meta_title">Meta title</label>
                <input type="text" name="meta_title" id="meta_title" class="form-control" maxlength="200"
                    value="<?= e((string) old('meta_title', $isEdit ? $page['meta_title'] : '')) ?>">
            </div>

            <div class="mb-0">
                <label class="form-label" for="meta_description">Meta description</label>
                <textarea name="meta_description" id="meta_description" class="form-control" rows="3"
                    maxlength="320"><?= e((string) old('meta_description', $isEdit ? $page['meta_description'] : '')) ?></textarea>
            </div>
        </div>

        <button class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Save changes' : 'Create page' ?>
        </button>
    </div>
</form>