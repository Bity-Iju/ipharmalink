<?php

/**
 * Write a review — /account/reviews/create
 *
 * @var string $entityType  'product' | 'pharmacy'
 * @var int    $targetId
 * @var array|null $subject
 * @var array $pending
 * @var array $errors
 */
$isPharmacy = $entityType === 'pharmacy';
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>'
        : '';
};

/** Renders a 1–5 star radio group. */
$stars = static function (string $name, string $label, bool $required = false): void {
    echo '<label class="form-label' . ($required ? ' required' : '') . '">' . e($label) . '</label>';
    echo '<div class="rating-stars d-flex flex-row-reverse justify-content-end mb-2">';
    for ($i = 5; $i >= 1; $i--) {
        $id = $name . $i;
        echo '<input type="radio" name="' . e($name) . '" id="' . e($id) . '" value="' . $i . '"'
            . ($required && $i === 5 ? ' required' : '') . '>'
            . '<label for="' . e($id) . '"><i class="bi bi-star"></i></label>';
    }
    echo '</div>';
};
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="ipl-card p-4">
            <h2 class="h5 fw-bold mb-1">Write a review</h2>
            <?php if ($subject !== null): ?>
                <p class="text-muted small mb-3">
                    About <strong><?= e((string) $subject['name']) ?></strong>
                </p>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small"><strong>Please correct the errors below.</strong></div>
            <?php endif; ?>

            <form method="post" action="/account/reviews/create" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                <input type="hidden" name="target_id" value="<?= (int) $targetId ?>">

                <div class="mb-3">
                    <?php $stars('rating', 'Overall rating', true); ?>
                    <?= $err('rating') ?>
                </div>

                <?php if ($isPharmacy): ?>
                    <hr>
                    <h3 class="h6 fw-bold mb-3">Rate the details</h3>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <?php $stars('service_rating', 'Service'); ?>
                        </div>
                        <div class="col-sm-4">
                            <?php $stars('availability_rating', 'Product availability'); ?>
                        </div>
                        <div class="col-sm-4">
                            <?php $stars('delivery_rating', 'Delivery experience'); ?>
                        </div>
                    </div>
                    <hr>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="title">Headline (optional)</label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="150"
                        placeholder="Sum up your experience" value="<?= old('title') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="body">Your review</label>
                    <textarea name="body" id="body" rows="5" class="form-control" required minlength="10"
                        placeholder="What did you buy? How was the service and delivery?"><?= old('body') ?></textarea>
                    <div class="form-text">At least 10 characters.</div>
                    <?= $err('body') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="image">Add a photo (optional)</label>
                    <input type="file" name="image" id="image" class="form-control" accept="image/*">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Publish review</button>
                    <a href="/account/reviews" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>