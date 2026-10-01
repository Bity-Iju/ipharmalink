<?php

/**
 * Product listing: /products, /search, /category/{slug}, /deals.
 *
 * @var \App\Paginator $paginator
 * @var array   $filters
 * @var array   $options   ['categories','pharmacies','brands','priceRange']
 * @var array|null $entity  category or brand, when applicable
 * @var string  $heading
 * @var int     $resultCount
 */
$products    = $paginator->items();
$priceRange  = $options['priceRange'] ?? ['min' => 0, 'max' => 0];
$hasFilters  = array_filter([
    $filters['q'] ?? '',
    $filters['category'] ?? 0,
    $filters['pharmacy'] ?? 0,
    $filters['brand'] ?? 0,
    $filters['min_price'] ?? '',
    $filters['max_price'] ?? '',
    $filters['in_stock'] ?? '',
    $filters['prescription'] ?? '',
]);
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <?php if (!empty($entity) && isset($entity['parent_id'])): ?>
                <li class="breadcrumb-item"><a href="/category/<?= e((string) $entity['slug']) ?>"><?= e((string) $entity['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Products</li>
            <?php elseif (!empty($entity) && isset($entity['icon'])): ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e((string) $entity['name']) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($heading) ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 section-title mb-1"><?= e($heading) ?></h1>
            <p class="text-muted small mb-0">
                <?php if (!empty($filters['q'])): ?>
                    <?= number_format($resultCount) ?> result<?= $resultCount === 1 ? '' : 's' ?>
                    for &ldquo;<?= e((string) $filters['q']) ?>&rdquo;
                <?php else: ?>
                    <?= number_format($resultCount) ?> product<?= $resultCount === 1 ? '' : 's' ?> available
                <?php endif; ?>
            </p>
        </div>

        <form method="get" class="d-flex align-items-center gap-2">
            <?php foreach (['q', 'category', 'pharmacy', 'brand', 'min_price', 'max_price', 'in_stock', 'prescription', 'class'] as $key): ?>
                <?php if (!empty($filters[$key])): ?>
                    <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $filters[$key]) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label class="text-muted small text-nowrap" for="sort">Sort by</label>
            <select name="sort" id="sort" class="form-select form-select-sm" data-auto-submit style="width:auto">
                <?php
                $sortOptions = [
                    'relevance'  => 'Most relevant',
                    'popularity' => 'Most popular',
                    'price_asc'  => 'Price: low to high',
                    'price_desc' => 'Price: high to low',
                    'newest'     => 'Newest first',
                    'rating'     => 'Top rated',
                    'name'       => 'Name A–Z',
                ];
                foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['sort'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="row g-4">
        <!-- ================= Filters ================= -->
        <div class="col-lg-3">
            <button class="btn btn-soft w-100 d-lg-none mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="bi bi-funnel me-1"></i> Filters
            </button>

            <div class="collapse d-lg-block filter-panel" id="filterPanel">
                <form method="get" action="<?= e(\App\Router::currentPath()) ?>">
                    <?php if (!empty($filters['q'])): ?>
                        <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="sort" value="<?= e((string) ($filters['sort'] ?? 'relevance')) ?>">

                    <!-- Availability -->
                    <div class="mb-3">
                        <h2 class="h6 fw-bold mb-2">Availability</h2>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="f-stock"
                                <?= ($filters['in_stock'] ?? '') === '1' ? ' checked' : '' ?> data-auto-submit>
                            <label class="form-check-label small" for="f-stock">In stock only</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="prescription" value="1" id="f-rx"
                                <?= ($filters['prescription'] ?? '') === '1' ? ' checked' : '' ?> data-auto-submit>
                            <label class="form-check-label small" for="f-rx">Prescription required</label>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="mb-3">
                        <h2 class="h6 fw-bold mb-2">Price range</h2>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="number" name="min_price" class="form-control form-control-sm" min="0" step="100"
                                placeholder="<?= (int) $priceRange['min'] ?>"
                                value="<?= e((string) ($filters['min_price'] ?? '')) ?>" aria-label="Minimum price">
                            <span class="text-muted">–</span>
                            <input type="number" name="max_price" class="form-control form-control-sm" min="0" step="100"
                                placeholder="<?= (int) $priceRange['max'] ?>"
                                value="<?= e((string) ($filters['max_price'] ?? '')) ?>" aria-label="Maximum price">
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <h2 class="h6 fw-bold mb-2">Category</h2>
                        <select name="category" class="form-select form-select-sm" data-auto-submit>
                            <option value="">All categories</option>
                            <?php foreach ($options['categories'] as $category): ?>
                                <?php if ($category['parent_id'] !== null) {
                                    continue;
                                } ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) ($filters['category'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $category['name']) ?> (<?= (int) $category['product_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pharmacy -->
                    <div class="mb-3">
                        <h2 class="h6 fw-bold mb-2">Pharmacy</h2>
                        <select name="pharmacy" class="form-select form-select-sm" data-auto-submit>
                            <option value="">All pharmacies</option>
                            <?php foreach ($options['pharmacies'] as $pharmacy): ?>
                                <option value="<?= (int) $pharmacy['id'] ?>" <?= (int) ($filters['pharmacy'] ?? 0) === (int) $pharmacy['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $pharmacy['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Brand -->
                    <?php if (!empty($options['brands'])): ?>
                        <div class="mb-3">
                            <h2 class="h6 fw-bold mb-2">Brand</h2>
                            <select name="brand" class="form-select form-select-sm" data-auto-submit>
                                <option value="">All brands</option>
                                <?php foreach ($options['brands'] as $brand): ?>
                                    <option value="<?= (int) $brand['id'] ?>" <?= (int) ($filters['brand'] ?? 0) === (int) $brand['id'] ? ' selected' : '' ?>>
                                        <?= e((string) $brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                        <?php if ($hasFilters): ?>
                            <a href="<?= e(\App\Router::currentPath()) ?>" class="btn btn-light btn-sm">Clear all</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= Results ================= -->
        <div class="col-lg-9">
            <?php if ($products === []): ?>
                <div class="ipl-card empty-state">
                    <div class="icon"><i class="bi bi-search"></i></div>
                    <h2 class="h5 fw-bold">No products matched your search</h2>
                    <p class="mb-3">Try a different keyword, widen your filters, or check the spelling of the medicine name.</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="/products" class="btn btn-primary btn-sm">Browse all products</a>
                        <a href="/categories" class="btn btn-light btn-sm">Browse categories</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <?php \App\View::include('components/product-card', ['product' => $product]); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php \App\View::include('components/pagination', [
                    'paginator' => $paginator,
                    'label'     => 'products',
                ]); ?>
            <?php endif; ?>
        </div>
    </div>
</div>