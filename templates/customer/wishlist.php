<?php

/**
 * Wishlist — /account/wishlist
 *
 * @var array $items
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">My wishlist</h2>
    <span class="text-muted small"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
</div>

<?php if (empty($items)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-heart"></i></div>
        <h3 class="h5 fw-bold">Your wishlist is empty</h3>
        <p class="mb-3">Tap the heart on any product to save it for later.</p>
        <a href="/products" class="btn btn-primary btn-sm">Browse products</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="position-relative">
                    <?php \App\View::include('components/product-card', ['product' => $item]); ?>

                    <div class="d-grid gap-1 mt-2">
                        <?php if ((int) $item['available'] === 1): ?>
                            <a href="/wishlist/move/<?= (int) $item['id'] ?>" class="btn btn-soft btn-sm">
                                <i class="bi bi-cart-plus me-1"></i> Move to cart
                            </a>
                        <?php else: ?>
                            <span class="btn btn-light btn-sm disabled">Currently unavailable</span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none"
                            data-wishlist="<?= (int) $item['id'] ?>" data-busy="0">
                            <i class="bi bi-heart-fill me-1"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>