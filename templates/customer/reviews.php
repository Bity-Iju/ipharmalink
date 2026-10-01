<?php

/**
 * My reviews — /account/reviews
 *
 * @var array $reviews
 * @var array $pending   delivered items with no review yet
 */
?>
<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h5 fw-bold mb-3">Reviews I have written</h2>

        <?php if (empty($reviews)): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-star"></i></div>
                <h3 class="h5 fw-bold">No reviews yet</h3>
                <p class="mb-0">After a delivery you can rate the products and the pharmacy.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($reviews as $review): ?>
                    <div class="ipl-card p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">
                                    <?= e((string) $review['subject']) ?>
                                </div>
                                <div class="text-muted small">
                                    <?= $review['entity_type'] === 'pharmacy' ? 'Pharmacy review' : 'Product review' ?>
                                </div>
                            </div>
                            <?= status_badge((string) $review['status']) ?>
                        </div>

                        <?= star_rating((float) $review['rating'], 0, false) ?>

                        <?php if (!empty($review['title'])): ?>
                            <h3 class="h6 fw-bold mt-2 mb-1"><?= e((string) $review['title']) ?></h3>
                        <?php endif; ?>
                        <p class="small text-muted mb-2"><?= e((string) $review['body']) ?></p>
                        <div class="text-muted small">
                            <?= e(date('j M Y', strtotime((string) $review['created_at']))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3" style="position:sticky;top:90px">
            <h2 class="h6 fw-bold mb-3">Waiting for your review</h2>

            <?php if (empty($pending)): ?>
                <div class="empty-state py-3">
                    <div class="icon"><i class="bi bi-check2-all"></i></div>
                    <p class="mb-0 small">You have reviewed everything delivered to you.</p>
                </div>
            <?php else: ?>
                <p class="small text-muted">
                    These items were delivered. Reviews help other customers shop confidently.
                </p>
                <div class="d-grid gap-2">
                    <?php foreach (array_slice($pending, 0, 12) as $item): ?>
                        <a href="/account/reviews/create?type=product&id=<?= (int) $item['product_id'] ?>"
                            class="btn btn-light btn-sm text-start d-flex justify-content-between align-items-center">
                            <span class="text-truncate me-2"><?= e((string) $item['product_name']) ?></span>
                            <i class="bi bi-star"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>