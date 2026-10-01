<?php

/**
 * A pharmacy's own storefront — /pharmacy/{slug}
 *
 * @var array $pharmacy
 * @var \App\Paginator $paginator
 * @var array $filters
 * @var array $categories
 * @var array $reviewSummary
 * @var array $reviews
 */
$products  = $paginator->items();
$openTime  = substr((string) $pharmacy['open_time'], 0, 5);
$closeTime = substr((string) $pharmacy['close_time'], 0, 5);
$now       = (int) date('Hi');
$isOpen    = $now >= (int) str_replace(':', '', $openTime) && $now <= (int) str_replace(':', '', $closeTime);
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/pharmacies">Pharmacies</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e((string) $pharmacy['name']) ?></li>
        </ol>
    </nav>

    <!-- ================= Storefront header ================= -->
    <div class="ipl-card overflow-hidden mb-4">
        <?php if (!empty($pharmacy['cover_image'])): ?>
            <div style="height:150px;background-image:url('<?= e(upload_url((string) $pharmacy['cover_image'])) ?>');background-size:cover;background-position:center"></div>
        <?php else: ?>
            <div style="height:150px;background:linear-gradient(135deg,#0a7d5f,#14b88a)"></div>
        <?php endif; ?>

        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <img src="<?= e(upload_url($pharmacy['logo'])) ?>" alt="<?= e((string) $pharmacy['name']) ?>"
                    width="84" height="84" class="rounded-3 border"
                    style="object-fit:contain;margin-top:-56px" onerror="this.src='/assets/images/placeholder.svg'">

                <div class="flex-grow-1 min-w-0">
                    <h1 class="h4 fw-bold mb-1"><?= e((string) $pharmacy['name']) ?></h1>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-geo-alt me-1"></i><?= e((string) $pharmacy['address']) ?>,
                        <?= e((string) $pharmacy['city']) ?>, <?= e((string) $pharmacy['state']) ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if ((int) $pharmacy['rating_count'] > 0): ?>
                            <?= star_rating((float) $pharmacy['rating_avg'], (int) $pharmacy['rating_count']) ?>
                        <?php endif; ?>
                        <span class="chip <?= $isOpen ? 'bg-success text-white' : '' ?>">
                            <i class="bi bi-circle-fill" style="font-size:.5rem"></i>
                            <?= $isOpen ? 'Open now' : 'Closed' ?> · <?= e($openTime) ?>–<?= e($closeTime) ?>
                        </span>
                        <?php if ((int) $pharmacy['is_featured'] === 1): ?>
                            <span class="badge bg-warning"><i class="bi bi-star-fill me-1"></i>Featured</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2" style="min-width:180px">
                    <a href="tel:<?= e((string) $pharmacy['phone']) ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-telephone me-1"></i> <?= e((string) $pharmacy['phone']) ?>
                    </a>
                    <?php if (!empty($pharmacy['website'])): ?>
                        <a href="<?= e((string) $pharmacy['website']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-light btn-sm">
                            <i class="bi bi-globe me-1"></i> Website
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($pharmacy['description'])): ?>
                <p class="text-muted small mt-3 mb-0" style="max-width:80ch">
                    <?= e((string) $pharmacy['description']) ?>
                </p>
            <?php endif; ?>

            <div class="row g-2 mt-3">
                <div class="col-sm-4">
                    <div class="ipl-card p-2 text-center h-100">
                        <i class="bi bi-box-seam text-brand"></i>
                        <div class="fw-bold"><?= (int) $pharmacy['product_count'] ?></div>
                        <div class="text-muted small">Products</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="ipl-card p-2 text-center h-100">
                        <i class="bi bi-truck text-brand"></i>
                        <div class="fw-bold"><?= money((float) $pharmacy['delivery_fee']) ?></div>
                        <div class="text-muted small">Delivery fee</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="ipl-card p-2 text-center h-100">
                        <i class="bi bi-clock-history text-brand"></i>
                        <div class="fw-bold">~<?= (int) $pharmacy['estimated_delivery_minutes'] ?> min</div>
                        <div class="text-muted small">Estimated delivery</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ================= Catalogue ================= -->
        <div class="col-lg-9">
            <form method="get" class="d-flex gap-2 mb-3">
                <input type="search" name="q" class="form-control" placeholder="Search this pharmacy’s products…"
                    value="<?= e((string) $filters['q']) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>

            <?php if (!empty($categories)): ?>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>"
                        class="chip <?= (int) ($filters['category'] ?? 0) === 0 ? 'bg-brand text-white' : '' ?>">All</a>
                    <?php foreach ($categories as $category): ?>
                        <a href="?category=<?= (int) $category['id'] ?>"
                            class="chip <?= (int) ($filters['category'] ?? 0) === (int) $category['id'] ? 'bg-brand text-white' : '' ?>">
                            <?= e((string) $category['name']) ?> (<?= (int) $category['product_count'] ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($products === []): ?>
                <div class="ipl-card empty-state">
                    <div class="icon"><i class="bi bi-box"></i></div>
                    <h2 class="h5 fw-bold">No products here yet</h2>
                    <p class="mb-0">This pharmacy has not listed anything matching your search.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <?php \App\View::include('components/product-card', ['product' => $product]); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'products']); ?>
            <?php endif; ?>
        </div>

        <!-- ================= Sidebar ================= -->
        <div class="col-lg-3">
            <div class="filter-panel">
                <h2 class="h6 fw-bold mb-2">How to order</h2>
                <ul class="list-unstyled small text-muted d-grid gap-2 mb-3">
                    <li><i class="bi bi-1-circle me-2 text-brand"></i>Add items to your cart</li>
                    <li><i class="bi bi-2-circle me-2 text-brand"></i>Choose delivery or pickup</li>
                    <li><i class="bi bi-3-circle me-2 text-brand"></i>Pay securely online</li>
                    <li><i class="bi bi-4-circle me-2 text-brand"></i>Track from your account</li>
                </ul>

                <hr>

                <h2 class="h6 fw-bold mb-2">Service</h2>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <?php if ((int) $pharmacy['delivery_available'] === 1): ?>
                        <span class="chip"><i class="bi bi-truck"></i> Home delivery</span>
                    <?php endif; ?>
                    <?php if ((int) $pharmacy['pickup_available'] === 1): ?>
                        <span class="chip"><i class="bi bi-bag-check"></i> Counter pickup</span>
                    <?php endif; ?>
                    <?php if ((float) $pharmacy['free_delivery_threshold'] > 0): ?>
                        <span class="chip">Free over <?= money((float) $pharmacy['free_delivery_threshold']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ((int) $reviewSummary['total'] > 0): ?>
                    <hr>
                    <h2 class="h6 fw-bold mb-2">Ratings</h2>
                    <div class="text-center mb-2">
                        <div class="fs-3 fw-bold"><?= e((string) $reviewSummary['average']) ?></div>
                        <?= star_rating((float) $reviewSummary['average'], (int) $reviewSummary['total'], false) ?>
                        <div class="text-muted small"><?= (int) $reviewSummary['total'] ?> reviews</div>
                    </div>
                    <div class="small">
                        <?php
                        $themes = [
                            'Service'         => $reviewSummary['service'],
                            'Availability'    => $reviewSummary['availability'],
                            'Delivery'        => $reviewSummary['delivery'],
                        ];
                        foreach ($themes as $label => $score):
                            if ((float) $score <= 0) {
                                continue;
                            } ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><?= e((string) $label) ?></span>
                                <span class="fw-semibold"><?= e((string) $score) ?> ★</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (\App\Auth::isCustomer()): ?>
                    <hr>
                    <a href="/account/reviews/create?type=pharmacy&id=<?= (int) $pharmacy['id'] ?>" class="btn btn-soft btn-sm w-100">
                        <i class="bi bi-star me-1"></i> Review this pharmacy
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================= Reviews ================= -->
    <?php if (!empty($reviews)): ?>
        <section class="mt-5 mb-4">
            <h2 class="section-title h5 mb-3">What customers say</h2>
            <div class="row g-3">
                <?php foreach ($reviews as $review): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="ipl-card p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="brand-mark" style="width:32px;height:32px;font-size:.75rem">
                                    <i class="bi bi-person"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="fw-semibold small text-truncate"><?= e((string) $review['author']) ?></div>
                                    <?= star_rating((float) $review['rating'], 0, false) ?>
                                </div>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                                <h3 class="h6 fw-bold mb-1"><?= e((string) $review['title']) ?></h3>
                            <?php endif; ?>
                            <p class="small text-muted mb-0"><?= e(str_excerpt((string) $review['body'], 220)) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>