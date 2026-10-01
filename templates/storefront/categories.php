<?php

/**
 * Category index — /categories
 *
 * @var array $categories
 */
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Categories</li>
        </ol>
    </nav>

    <h1 class="h3 section-title">Product categories</h1>
    <p class="text-muted mb-4">Browse the full catalogue by what you need.</p>

    <div class="row g-3">
        <?php foreach ($categories as $category): ?>
            <div class="col-md-6 col-lg-4">
                <div class="ipl-card p-3 h-100">
                    <a href="/category/<?= e((string) $category['slug']) ?>" class="d-flex align-items-center gap-3 text-reset">
                        <span class="category-tile flex-grow-1">
                            <span class="icon"><i class="bi <?= e((string) ($category['icon'] ?: 'bi-tags')) ?>"></i></span>
                            <span class="min-w-0">
                                <span class="d-block text-truncate"><?= e((string) $category['name']) ?></span>
                                <small class="text-muted fw-normal">
                                    <?= (int) $category['product_count'] ?> product<?= (int) $category['product_count'] === 1 ? '' : 's' ?>
                                </small>
                            </span>
                        </span>
                    </a>

                    <?php if (!empty($category['description'])): ?>
                        <p class="small text-muted mt-2 mb-0"><?= e(str_excerpt((string) $category['description'], 130)) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($category['children'])): ?>
                        <hr class="my-2">
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($category['children'] as $child): ?>
                                <a href="/category/<?= e((string) $child['slug']) ?>" class="chip"><?= e((string) $child['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>