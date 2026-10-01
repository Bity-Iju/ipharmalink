<?php

/**
 * FAQ — /faq
 *
 * @var array $faqs
 * @var array $groups
 * @var string $currentGroup
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Help &amp; FAQ</li>
                </ol>
            </nav>

            <h1 class="h3 section-title">Frequently asked questions</h1>
            <p class="text-muted mb-4">Answers about ordering, delivery, prescriptions, payments and refunds.</p>

            <?php if ($currentGroup === '' && count($groups) > 1): ?>
                <div class="d-flex flex-wrap gap-1 mb-4">
                    <a href="/faq" class="chip bg-brand text-white">All topics</a>
                    <?php foreach ($groups as $group): ?>
                        <a href="/faq?group=<?= e(urlencode((string) $group['category'])) ?>" class="chip">
                            <?= e((string) $group['category']) ?> (<?= (int) $group['total'] ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="accordion" id="faqAccordion">
                <?php foreach ($faqs as $i => $faq): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $faq['id'] ?>"
                                aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"
                                aria-controls="faq<?= (int) $faq['id'] ?>">
                                <?= e((string) $faq['question']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= (int) $faq['id'] ?>"
                            class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>"
                            data-bs-parent="#faqAccordion">
                            <div class="accordion-body small text-muted" style="white-space:pre-line">
                                <?= e((string) $faq['answer']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($faqs === []): ?>
                <div class="ipl-card empty-state">
                    <div class="icon"><i class="bi bi-question-circle"></i></div>
                    <h2 class="h5 fw-bold">No questions published yet</h2>
                    <p class="mb-3">Our support team is happy to help in the meantime.</p>
                    <a href="/contact" class="btn btn-primary btn-sm">Contact support</a>
                </div>
            <?php endif; ?>

            <div class="ipl-card p-4 text-center mt-4">
                <h2 class="h5 fw-bold">Still need help?</h2>
                <p class="text-muted small mb-3">Our support team replies within one business day.</p>
                <a href="/contact" class="btn btn-primary btn-sm">Send us a message</a>
            </div>
        </div>
    </div>
</div>