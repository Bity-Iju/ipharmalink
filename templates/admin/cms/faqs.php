<?php

/**
 * Admin FAQs — /admin/faqs
 *
 * @var array $faqs
 */
?>
<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h5 fw-bold mb-3">FAQs <span class="text-muted fw-normal">(<?= count($faqs) ?>)</span></h2>

        <?php if ($faqs === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-question-circle"></i></div>
                <h3 class="h5 fw-bold">No questions yet</h3>
                <p class="mb-0">Add the questions your buyers ask most.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-2">
                <?php foreach ($faqs as $faq): ?>
                    <div class="ipl-card p-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h3 class="h6 fw-bold mb-1"><?= e((string) $faq['question']) ?></h3>
                                <span class="chip"><?= e((string) $faq['category']) ?></span>
                            </div>
                            <?= status_badge((int) $faq['is_active'] === 1 ? 'published' : 'draft') ?>
                        </div>

                        <p class="small text-muted mb-2"><?= e(str_excerpt((string) $faq['answer'], 220)) ?></p>

                        <div class="d-flex gap-1">
                            <form method="post" action="/admin/faqs/<?= (int) $faq['id'] ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="category" value="<?= e((string) $faq['category']) ?>">
                                <input type="hidden" name="question" value="<?= e((string) $faq['question']) ?>">
                                <input type="hidden" name="answer" value="<?= e((string) $faq['answer']) ?>">
                                <input type="hidden" name="sort_order" value="<?= (int) $faq['sort_order'] ?>">
                                <input type="hidden" name="is_active" value="<?= (int) $faq['is_active'] === 1 ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-light" title="<?= (int) $faq['is_active'] === 1 ? 'Hide' : 'Show' ?>">
                                    <i class="bi bi-eye<?= (int) $faq['is_active'] === 1 ? '' : '-slash' ?>"></i>
                                </button>
                            </form>

                            <form method="post" action="/admin/faqs/<?= (int) $faq['id'] ?>/delete" class="m-0"
                                data-confirm="Delete this question?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="ipl-card p-3">
            <h3 class="h6 fw-bold mb-3">Add a question</h3>

            <form method="post" action="/admin/faqs" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="faq_category">Category</label>
                    <input type="text" name="category" id="faq_category" class="form-control"
                        value="<?= e((string) old('category', 'General')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="faq_question">Question</label>
                    <input type="text" name="question" id="faq_question" class="form-control" required
                        maxlength="255" value="<?= e(old('question')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="faq_answer">Answer</label>
                    <textarea name="answer" id="faq_answer" class="form-control" rows="5" required><?= e(old('answer')) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="faq_sort">Sort order</label>
                    <input type="number" name="sort_order" id="faq_sort" class="form-control" value="0">
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="faq_active"
                        value="1" checked>
                    <label class="form-check-label" for="faq_active">Visible on the site</label>
                </div>

                <button class="btn btn-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i> Add question
                </button>
            </form>
        </div>
    </div>
</div>