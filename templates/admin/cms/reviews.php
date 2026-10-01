<?php

/**
 * Review moderation — /admin/reviews
 *
 * @var \App\Paginator $paginator
 * @var string $status
 */
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'published' => 'Published', 'rejected' => 'Rejected'] as $value => $label): ?>
        <a href="/admin/reviews<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($paginator->items() === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-star"></i></div>
        <h3 class="h5 fw-bold">No reviews here</h3>
    </div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($paginator->items() as $review): ?>
            <div class="ipl-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-semibold">
                            <?= e((string) $review['author']) ?>
                            <span class="text-muted fw-normal">reviewed</span>
                            <a href="<?= $review['entity_type'] === 'pharmacy'
                                            ? '/admin/pharmacies/' . (int) $review['pharmacy_id']
                                            : '/admin/products/' . (int) $review['product_id'] ?>" class="fw-semibold">
                                <?= e(str_excerpt((string) ($review['subject'] ?? 'item'), 40)) ?>
                            </a>
                        </div>
                        <div class="text-muted small">
                            <?= e(ucfirst((string) $review['entity_type'])) ?>
                            · <?= e(date('j M Y', strtotime((string) $review['created_at']))) ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?= star_rating((float) $review['rating'], 0, false) ?>
                        <?= status_badge((string) $review['status']) ?>
                    </div>
                </div>

                <?php if (!empty($review['title'])): ?>
                    <h3 class="h6 fw-bold mb-1"><?= e((string) $review['title']) ?></h3>
                <?php endif; ?>
                <p class="small text-muted"><?= e((string) $review['body']) ?></p>

                <?php $themes = array_filter([
                    'Service' => $review['service_rating'],
                    'Availability' => $review['availability_rating'],
                    'Delivery' => $review['delivery_rating'],
                ], static fn($v): bool => $v !== null); ?>
                <?php if ($themes !== []): ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($themes as $label => $score): ?>
                            <span class="chip"><?= e((string) $label) ?>: <?= (int) $score ?> ★</span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/admin/reviews/<?= (int) $review['id'] ?>" class="d-inline-flex gap-1">
                    <?= csrf_field() ?>
                    <?php if ((string) $review['status'] !== 'published'): ?>
                        <button name="status" value="published" class="btn btn-sm btn-success">
                            <i class="bi bi-check-lg me-1"></i>Publish
                        </button>
                    <?php endif; ?>
                    <?php if ((string) $review['status'] !== 'rejected'): ?>
                        <button name="status" value="rejected" class="btn btn-sm btn-outline-danger"
                            data-confirm="Reject this review?">
                            <i class="bi bi-x-lg me-1"></i>Reject
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'reviews']); ?>
<?php endif; ?>