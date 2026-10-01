<?php

/**
 * Customer reviews for the pharmacy — /pharmacy/reviews
 *
 * @var array $summary
 * @var array $reviews
 */
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="ipl-card p-4 text-center">
            <h2 class="h5 fw-bold mb-3">Your rating</h2>
            <div class="fs-1 fw-bold"><?= e((string) ($summary['average'] ?? '0')) ?></div>
            <?= star_rating((float) ($summary['average'] ?? 0), (int) ($summary['total'] ?? 0), false) ?>
            <p class="text-muted small mt-2 mb-0"><?= (int) ($summary['total'] ?? 0) ?> review(s)</p>

            <hr>

            <?php
            $themes = [
                'Service'      => $summary['service'] ?? 0,
                'Availability' => $summary['availability'] ?? 0,
                'Delivery'     => $summary['delivery'] ?? 0,
            ];
            foreach ($themes as $label => $score):
                if ((float) $score <= 0) {
                    continue;
                } ?>
                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted"><?= e((string) $label) ?></span>
                    <span class="fw-semibold"><?= e((string) $score) ?> ★</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <h2 class="h5 fw-bold mb-3">What customers say</h2>

        <?php if (empty($reviews)): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-star"></i></div>
                <h3 class="h5 fw-bold">No reviews yet</h3>
                <p class="mb-0">Delivered customers can rate your service.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($reviews as $review): ?>
                    <div class="ipl-card p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="brand-mark" style="width:34px;height:34px;font-size:.8rem">
                                    <i class="bi bi-person"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold small"><?= e((string) $review['author']) ?></div>
                                    <?= star_rating((float) $review['rating'], 0, false) ?>
                                </div>
                            </div>
                            <span class="text-muted small"><?= e(date('j M Y', strtotime((string) $review['created_at']))) ?></span>
                        </div>

                        <?php if (!empty($review['title'])): ?>
                            <h3 class="h6 fw-bold mb-1"><?= e((string) $review['title']) ?></h3>
                        <?php endif; ?>
                        <p class="small text-muted"><?= e((string) $review['body']) ?></p>

                        <?php $themes = array_filter([
                            'Service'      => $review['service_rating'],
                            'Availability' => $review['availability_rating'],
                            'Delivery'     => $review['delivery_rating'],
                        ], static fn($v): bool => $v !== null); ?>
                        <?php if ($themes !== []): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($themes as $label => $score): ?>
                                    <span class="chip"><?= e((string) $label) ?>: <?= (int) $score ?> ★</span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>