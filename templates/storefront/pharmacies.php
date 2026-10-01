<?php

/**
 * Pharmacy directory — /pharmacies
 *
 * @var \App\Paginator $paginator
 * @var array $filters
 * @var array $states
 */
$pharmacies = $paginator->items();
?>
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 section-title">Pharmacy directory</h1>
        <p class="text-muted mb-0">
            Every pharmacy here is licensed and reviewed by our compliance team before it can list products.
        </p>
    </div>

    <form method="get" action="/pharmacies" class="ipl-card p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="q">Search</label>
                <input type="search" name="q" id="q" class="form-control" placeholder="Pharmacy, city or area…"
                    value="<?= e((string) $filters['q']) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="state">State</label>
                <select name="state" id="state" class="form-select" data-auto-submit>
                    <option value="">All states</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?= e((string) $state['state']) ?>" <?= (string) $filters['state'] === (string) $state['state'] ? ' selected' : '' ?>>
                            <?= e((string) $state['state']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="service">Service</label>
                <select name="service" id="service" class="form-select" data-auto-submit>
                    <option value="">Any service</option>
                    <option value="delivery" <?= (string) $filters['service'] === 'delivery' ? ' selected' : '' ?>>Offers delivery</option>
                    <option value="pickup" <?= (string) $filters['service'] === 'pickup' ? ' selected' : '' ?>>Offers pickup</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </div>
    </form>

    <?php if ($pharmacies === []): ?>
        <div class="ipl-card empty-state">
            <div class="icon"><i class="bi bi-shop"></i></div>
            <h2 class="h5 fw-bold">No pharmacies matched your search</h2>
            <p class="mb-3">Try a different area or clear the filters to see all pharmacies.</p>
            <a href="/pharmacies" class="btn btn-primary btn-sm">Show all pharmacies</a>
        </div>
    <?php else: ?>
        <p class="text-muted small"><?= number_format($paginator->total()) ?> pharmacies</p>

        <div class="row g-3">
            <?php foreach ($pharmacies as $pharmacy): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="ipl-card pharmacy-card h-100 overflow-hidden">
                        <?php if (!empty($pharmacy['cover_image'])): ?>
                            <div class="banner" style="background-image:url('<?= e(upload_url((string) $pharmacy['cover_image'])) ?>');background-size:cover;background-position:center"></div>
                        <?php else: ?>
                            <div class="banner"></div>
                        <?php endif; ?>

                        <div class="card-body pt-0">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <img src="<?= e(upload_url($pharmacy['logo'])) ?>" alt=""
                                    class="avatar" onerror="this.src='/assets/images/placeholder.svg'">
                                <div class="min-w-0 flex-grow-1">
                                    <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" class="text-reset">
                                        <h3 class="h6 fw-bold mb-1 text-truncate"><?= e((string) $pharmacy['name']) ?></h3>
                                    </a>
                                    <div class="small text-muted text-truncate">
                                        <i class="bi bi-geo-alt me-1"></i><?= e((string) $pharmacy['city']) ?>, <?= e((string) $pharmacy['state']) ?>
                                    </div>
                                </div>
                                <?php if ((int) $pharmacy['is_featured'] === 1): ?>
                                    <span class="badge bg-warning" title="Featured"><i class="bi bi-star-fill"></i></span>
                                <?php endif; ?>
                            </div>

                            <p class="small text-muted mb-2"><?= e(str_excerpt((string) $pharmacy['description'], 110)) ?></p>

                            <div class="d-flex align-items-center justify-content-between small mb-2">
                                <?php if ((int) $pharmacy['rating_count'] > 0): ?>
                                    <?= star_rating((float) $pharmacy['rating_avg'], (int) $pharmacy['rating_count']) ?>
                                <?php else: ?>
                                    <span class="text-muted">New on the platform</span>
                                <?php endif; ?>
                                <span class="text-muted"><?= (int) $pharmacy['product_count'] ?> products</span>
                            </div>

                            <div class="d-flex flex-wrap gap-1 mb-3 small">
                                <span class="chip"><i class="bi bi-clock"></i>
                                    <?= e(substr((string) $pharmacy['open_time'], 0, 5)) ?>–<?= e(substr((string) $pharmacy['close_time'], 0, 5)) ?>
                                </span>
                                <?php if ((int) $pharmacy['delivery_available'] === 1): ?>
                                    <span class="chip"><i class="bi bi-truck"></i> <?= money((float) $pharmacy['delivery_fee']) ?></span>
                                    <span class="chip"><i class="bi bi-clock-history"></i> <?= (int) $pharmacy['estimated_delivery_minutes'] ?> min</span>
                                <?php else: ?>
                                    <span class="chip"><i class="bi bi-shop"></i> Pickup only</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="/pharmacy/<?= e((string) $pharmacy['slug']) ?>" class="btn btn-primary btn-sm flex-grow-1">
                                    Visit store
                                </a>
                                <a href="tel:<?= e((string) $pharmacy['phone']) ?>" class="btn btn-light btn-sm" title="Call">
                                    <i class="bi bi-telephone"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'pharmacies']); ?>
    <?php endif; ?>
</div>