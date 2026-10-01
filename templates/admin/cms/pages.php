<?php

/**
 * Admin static pages — /admin/pages
 *
 * @var array $pages
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Pages <span class="text-muted fw-normal">(<?= count($pages) ?>)</span></h2>
    <a href="/admin/pages/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New page
    </a>
</div>

<?php if ($pages === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
        <h3 class="h5 fw-bold">No pages yet</h3>
        <p class="mb-0">Create your Terms, Privacy Policy and help content here.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Last updated</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string) $page['title']) ?></td>
                            <td class="small text-muted">/<?= e((string) $page['slug']) ?></td>
                            <td class="small text-muted">
                                <?= e(date('j M Y', strtotime((string) $page['updated_at']))) ?>
                            </td>
                            <td><?= status_badge((int) $page['is_published'] === 1 ? 'published' : 'draft') ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="/admin/pages/<?= (int) $page['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ((int) $page['is_published'] === 1): ?>
                                        <a href="/<?= e((string) $page['slug']) ?>" class="btn btn-sm btn-light" title="View live">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete" class="m-0"
                                        data-confirm="Delete this page?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
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