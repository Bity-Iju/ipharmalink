<?php

/**
 * Storefront homepage.
 *
 * @var array $banners
 * @var array $categories
 * @var array $featuredPharmacies
 * @var array $popularProducts
 * @var array $newProducts
 * @var array $deals
 * @var array $healthProducts
 * @var array $stats
 */
?>

<section class="container pt-4">
    <?php if (!empty($banners)): ?>
        <div id="homeHero" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($banners as $i => $banner): ?>
                    <button type="button" data-bs-target="#homeHero" data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>"
                        aria-label="Slide <?= $i + 1 ?>"
                        <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner rounded-4 overflow-hidden">
                <?php foreach ($banners as $i => $banner): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <div class="ipl-hero">
                            <div class="row align-items-center g-4">
                                <div class="col-lg-7">
                                    <h1><?= e((string) $banner['title']) ?></h1>
                                    <?php if (!empty($banner['subtitle'])): ?>
                                        <p><?= e((string) $banner['subtitle']) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <?php if (!empty($banner['button_text'])): ?>
                                            <a href="<?= e((string) ($banner['button_link'] ?: '/products')) ?>" class="btn btn-light btn-lg">
                                                <?= e((string) $banner['button_text']) ?> <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="/pharmacies" class="btn btn-outline-light btn-lg">Browse pharmacies</a>
                                    </div>
                                </div>
                                <?php if (!empty($banner['image'])): ?>
                                    <div class="col-lg-5 d-none d-lg-block text-center">
                                        <img src="<?= e(upload_url((string) $banner['image'])) ?>" alt=""
                                            class="img-fluid rounded-4" style="max-height:230px;object-fit:contain">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($banners) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#homeHero" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#homeHero" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span>
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ipl-hero">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h1>Genuine medicines, delivered from verified pharmacies</h1>
                    <p>Order prescription and over-the-counter medicines, vitamins and health products from licensed
                        pharmacies near you. Choose home delivery or collect at the counter.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="/products" class="btn btn-light btn-lg">Shop medicines <i class="bi bi-arrow-right ms-1"></i></a>
                        <a href="/pharmacies" class="btn btn-outline-light btn-lg">Find a pharmacy</a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="fs-2 fw-bold"><?= (int) ($stats['pharmacies'] ?? 0) ?></div>
                            <div class="small opacity-75">Pharmacies</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-2 fw-bold"><?= number_format((int) ($stats['products'] ?? 0)) ?></div>
                            <div class="small opacity-75">Products</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-2 fw-bold"><?= number_format((int) ($stats['orders'] ?? 0)) ?></div>
                            <div class="small opacity-75">Delivered</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="text-end mt-2 small text-muted">
        Wholesale supplier? <a href="/supplier/register">Register your business</a>
    </div>
</section>

<?php if (!empty($categories)): ?>
    <section class="container mt-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="section-title h4 mb-0">Shop by category <small class="d-none d-sm-inline">— what do you need today?</small></h2>
            <a href="/categories" class="section-link">All categories <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-2 g-md-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a class="category-tile" href="/category/<?= e((string) $category['slug']) ?>">
                        <span class="icon">
                            <i class="bi <?= e((string) ($category['icon'] ?: 'bi-tags')) ?>"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="d-block text-truncate"><?= e((string) $category['name']) ?></span>
                            <small class="text-muted fw-normal"><?= (int) $category['product_count'] ?> item<?= (int) $category['product_count'] === 1 ? '' : 's' ?></small>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($featuredPharmacies)): ?>
    <section class="container mt-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="section-title h4 mb-0">Featured pharmacies</h2>
            <a href="/pharmacies" class="section-link">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-3">
            <?php foreach ($featuredPharmacies as $pharmacy): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="ipl-card pharmacy-card h-100 overflow-hidden">
                        <div class="banner"></div>
                        <div class="card-body pt-0">
                            <div class="d-flex align-items-start gap-2">
                                <img src="<?= e(upload_url($pharmacy['logo'])) ?>" alt=""
                                    class="avatar" onerror="this.src='/assets/images/placeholder.svg'">
                                <div class="min-w-0 flex-grow-1">
                                    <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" class="text-reset d-block">
                                        <h3 class="h6 fw-bold mb-1 text-truncate"><?= e((string) $pharmacy['name']) ?></h3>
                                    </a>
                                    <div class="small text-muted">
                                        <i class="bi bi-geo-alt me-1"></i><?= e((string) $pharmacy['city']) ?>, <?= e((string) $pharmacy['state']) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 my-2 small">
                                <?= star_rating((float) $pharmacy['rating_avg'], (int) $pharmacy['rating_count']) ?>
                            </div>

                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <?php if ((int) $pharmacy['delivery_available'] === 1): ?>
                                    <span class="chip"><i class="bi bi-truck"></i> Delivery · <?= money((float) $pharmacy['delivery_fee']) ?></span>
                                <?php else: ?>
                                    <span class="chip"><i class="bi bi-shop"></i> Pickup only</span>
                                <?php endif; ?>
                                <?php if ((int) $pharmacy['pickup_available'] === 1): ?>
                                    <span class="chip"><i class="bi bi-bag-check"></i> Pickup</span>
                                <?php endif; ?>
                            </div>

                            <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" class="btn btn-soft btn-sm w-100">
                                Visit storefront
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php
/** Renders a product rail; reused for popular, new, deals and health. */
$rails = [
    ['deals',            'Deals &amp; discounts', 'Save on genuine products right now', 'bi-lightning-charge-fill'],
    ['popularProducts',  'Popular medicines',     'What customers are buying most', 'bi-fire'],
    ['newProducts',      'New arrivals',          'Recently added by our pharmacies', 'bi-stars'],
];
foreach ($rails as [$key, $railTitle, $railSubtitle, $railIcon]): ?>
    <?php if (!empty($$key)): ?>
        <section class="container mt-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="section-title h4 mb-0">
                    <i class="bi <?= e($railIcon) ?> text-brand me-1"></i><?= $railTitle ?>
                    <small class="d-none d-sm-inline">— <?= e($railSubtitle) ?></small>
                </h2>
                <a href="/products" class="section-link">See all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-3">
                <?php foreach (array_slice($$key, 0, 6) as $product): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <?php \App\View::include('components/product-card', ['product' => $product]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!empty($healthProducts)): ?>
    <section class="container mt-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="section-title h4 mb-0">Health &amp; wellness <small class="d-none d-sm-inline">— devices, supplements and personal care</small></h2>
            <a href="/search?q=vitamin" class="section-link">Shop health <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-3">
            <?php foreach (array_slice($healthProducts, 0, 6) as $product): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <?php \App\View::include('components/product-card', ['product' => $product]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="container mt-5 mb-4">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="ipl-card h-100 p-4">
                <span class="brand-mark mb-3" style="width:44px;height:44px;font-size:1.3rem">
                    <i class="bi bi-search"></i>
                </span>
                <h3 class="h5 fw-bold">1. Find your medicine</h3>
                <p class="text-muted mb-0 small">
                    Search by brand, generic name or active ingredient. Our filters help you compare
                    price, pack size and the pharmacy that has it in stock.
                </p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ipl-card h-100 p-4">
                <span class="brand-mark mb-3" style="width:44px;height:44px;font-size:1.3rem">
                    <i class="bi bi-clipboard-check"></i>
                </span>
                <h3 class="h5 fw-bold">2. We verify the pharmacy</h3>
                <p class="text-muted mb-0 small">
                    Every pharmacy is vetted before it can list products. Prescription items are
                    reviewed by a licensed pharmacist before dispensing.
                </p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ipl-card h-100 p-4">
                <span class="brand-mark mb-3" style="width:44px;height:44px;font-size:1.3rem">
                    <i class="bi bi-box-seam"></i>
                </span>
                <h3 class="h5 fw-bold">3. Delivered or collected</h3>
                <p class="text-muted mb-0 small">
                    Have it delivered to your door, or collect at the pharmacy counter. Track your
                    order from our app or your account at any time.
                </p>
            </div>
        </div>
    </div>
</section>