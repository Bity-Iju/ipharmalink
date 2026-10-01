<?php
/** @var array<string,mixed> $supplier */
/** @var list<array<string,mixed>> $documents */
?>
<section class="ipl-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="text-muted small text-uppercase fw-semibold mb-1">Verified wholesale supplier</div>
            <h2 class="h4 mb-1"><?= e($supplier['name']) ?></h2>
            <div class="text-muted"><?= e($supplier['registered_name']) ?></div>
        </div>
        <span class="badge text-bg-success">Approved</span>
    </div>
</section>

<section class="ipl-card p-4">
    <h2 class="h6 fw-bold mb-3">Business profile</h2>
    <dl class="row mb-0">
        <dt class="col-sm-4 text-muted">Registration number</dt>
        <dd class="col-sm-8"><?= e($supplier['registration_number']) ?></dd>
        <dt class="col-sm-4 text-muted">Responsible pharmacist</dt>
        <dd class="col-sm-8"><?= e($supplier['pharmacist_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Contact</dt>
        <dd class="col-sm-8"><?= e($supplier['contact_person']) ?> · <?= e($supplier['phone']) ?> · <?= e($supplier['email']) ?></dd>
        <dt class="col-sm-4 text-muted">Location</dt>
        <dd class="col-sm-8"><?= e($supplier['city']) ?>, <?= e($supplier['state']) ?></dd>
        <dt class="col-sm-4 text-muted">Verification documents</dt>
        <dd class="col-sm-8"><?= count($documents) ?> document(s) on file</dd>
    </dl>
</section>