<?php

/**
 * Pagination control.
 *
 * @var \App\Paginator $paginator
 * @var string|null    $label
 */
$paginator = $paginator ?? null;
$label     = $label ?? 'results';
if ($paginator === null || !$paginator->hasPages()) {
    if ($paginator !== null) {
        printf(
            '<p class="text-muted small mb-0">Showing %d of %d %s</p>',
            $paginator->count(),
            $paginator->total(),
            e($label)
        );
    }
    return;
}
$window = $paginator->window(2);
?>
<nav aria-label="Pagination" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
    <p class="text-muted small mb-0">
        Showing <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> of <?= (int) $paginator->total() ?> <?= e($label) ?>
    </p>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item<?= $paginator->hasPreviousPage() ? '' : ' disabled' ?>">
            <a class="page-link" href="<?= e($paginator->url($paginator->previousPage())) ?>" aria-label="Previous">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php foreach ($window as $page): ?>
            <?php if (is_string($page)): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
                <li class="page-item<?= $page === $paginator->currentPage() ? ' active' : '' ?>">
                    <a class="page-link" href="<?= e($paginator->url($page)) ?>"><?= $page ?></a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item<?= $paginator->hasNextPage() ? '' : ' disabled' ?>">
            <a class="page-link" href="<?= e($paginator->url($paginator->nextPage())) ?>" aria-label="Next">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>