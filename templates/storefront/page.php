<?php

/**
 * CMS content page — used for /about, /terms, /privacy and every /page/{slug}.
 *
 * The body is admin-authored HTML. It is rendered unescaped on purpose, so
 * CmsAdminController strips <script>, <style> and event handlers before save.
 *
 * @var array|null $page
 * @var string    $heading
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($heading) ?></li>
                </ol>
            </nav>

            <h1 class="h3 section-title mb-4"><?= e($heading) ?></h1>

            <?php if ($page === null): ?>
                <div class="ipl-card empty-state">
                    <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                    <h2 class="h5 fw-bold">This page has not been written yet</h2>
                    <p class="mb-3">An administrator can add content for this page from the admin panel.</p>
                    <a href="/contact" class="btn btn-primary btn-sm">Contact us</a>
                </div>
            <?php else: ?>
                <div class="ipl-card p-4 p-lg-5">
                    <div class="fs-5" style="line-height:1.8"><?= $page['body'] ?></div>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    Last updated <?= e(date('j F Y', strtotime((string) $page['updated_at']))) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>