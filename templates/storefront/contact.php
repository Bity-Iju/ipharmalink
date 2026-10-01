<?php

/**
 * Contact form — /contact
 *
 * @var string $heading
 * @var array  $errors  from the validator
 */
$fieldErrors = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e(implode(' ', $errors[$field])) . '</div>' : '';
};
?>
<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-lg-7">
            <h1 class="h3 section-title mb-2"><?= e($heading ?? 'Contact us') ?></h1>
            <p class="text-muted mb-4">Questions about an order, a pharmacy, or joining the platform? Tell us here.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1 small">
                        <?php foreach ($errors as $field => $messages): ?>
                            <?php foreach ($messages as $message): ?>
                                <li><?= e($message) ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/contact" class="ipl-card p-4">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="name">Full name</label>
                        <input type="text" name="name" id="name" class="form-control" required
                            value="<?= old('name') ?>">
                        <?= $fieldErrors('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="email">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                            value="<?= old('email') ?>">
                        <?= $fieldErrors('email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="0803 000 0000"
                            value="<?= old('phone') ?>">
                        <?= $fieldErrors('phone') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="subject">Subject</label>
                        <input type="text" name="subject" id="subject" class="form-control" required
                            value="<?= old('subject') ?>">
                        <?= $fieldErrors('subject') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="message">Message</label>
                        <textarea name="message" id="message" rows="5" class="form-control" required><?= old('message') ?></textarea>
                        <?= $fieldErrors('message') ?>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Send message
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="ipl-card p-4">
                <h2 class="h6 fw-bold mb-3">Other ways to reach us</h2>
                <ul class="list-unstyled small d-grid gap-3">
                    <li class="d-flex gap-2">
                        <i class="bi bi-telephone text-brand"></i>
                        <span>
                            <strong class="d-block">Phone</strong>
                            <?= e(\App\Setting::getString('general.support_phone', 'Not set')) ?>
                        </span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="bi bi-envelope text-brand"></i>
                        <span>
                            <strong class="d-block">Email</strong>
                            <?= e(\App\Setting::getString('general.support_email', 'Not set')) ?>
                        </span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="bi bi-geo-alt text-brand"></i>
                        <span>
                            <strong class="d-block">Head office</strong>
                            <?= e(\App\Setting::getString('general.address', 'Not set')) ?>
                        </span>
                    </li>
                </ul>

                <hr>

                <h2 class="h6 fw-bold mb-2">Quick answers</h2>
                <div class="d-grid gap-2">
                    <a href="/faq" class="btn btn-light btn-sm">Read the FAQ</a>
                    <a href="/refund-policy" class="btn btn-light btn-sm">Refund policy</a>
                    <a href="/delivery-policy" class="btn btn-light btn-sm">Delivery policy</a>
                </div>
            </div>
        </div>
    </div>
</div>