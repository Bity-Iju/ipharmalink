<?php
/** @var array<string,mixed> $supplier */
/** @var list<array<string,mixed>> $documents */
/** @var list<string> $statuses */
$statusLabels = ['pending' => 'Pending', 'under_review' => 'Under review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'deactivated' => 'Deactivated'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div><div class="text-muted small">Supplier verification</div><h2 class="h4 mb-0"><?= e($supplier['name']) ?></h2></div>
    <span class="badge text-bg-<?= $supplier['status'] === 'approved' ? 'success' : (in_array($supplier['status'], ['rejected', 'suspended', 'deactivated'], true) ? 'danger' : 'warning') ?>"><?= e($statusLabels[$supplier['status']] ?? $supplier['status']) ?></span>
</div>

<section class="ipl-card p-4 mb-3">
    <h3 class="h6 fw-bold mb-3">Business information</h3>
    <dl class="row mb-0">
        <dt class="col-sm-4 text-muted">Registered name</dt><dd class="col-sm-8"><?= e($supplier['registered_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Registration number</dt><dd class="col-sm-8"><?= e($supplier['registration_number']) ?></dd>
        <dt class="col-sm-4 text-muted">Owner account</dt><dd class="col-sm-8"><?= e($supplier['owner_name']) ?> · <?= e($supplier['owner_email']) ?></dd>
        <dt class="col-sm-4 text-muted">Pharmacist / owner</dt><dd class="col-sm-8"><?= e($supplier['pharmacist_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Contact</dt><dd class="col-sm-8"><?= e($supplier['contact_person']) ?> · <?= e($supplier['phone']) ?> · <?= e($supplier['email']) ?></dd>
        <dt class="col-sm-4 text-muted">Location</dt><dd class="col-sm-8"><?= e($supplier['business_address']) ?>, <?= e($supplier['city']) ?>, <?= e($supplier['state']) ?></dd>
        <dt class="col-sm-4 text-muted">Warehouse</dt><dd class="col-sm-8"><?= e($supplier['warehouse_address'] ?: 'Not supplied') ?></dd>
        <dt class="col-sm-4 text-muted">Description</dt><dd class="col-sm-8"><?= nl2br(e($supplier['description'] ?? '')) ?></dd>
        <?php if ($supplier['status_reason']): ?><dt class="col-sm-4 text-muted">Previous decision note</dt><dd class="col-sm-8"><?= e($supplier['status_reason']) ?></dd><?php endif; ?>
    </dl>
</section>

<section class="ipl-card p-4 mb-3">
    <h3 class="h6 fw-bold mb-3">Verification documents</h3>
    <?php if ($documents === []): ?><p class="text-muted mb-0">No documents were uploaded.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Type</th><th>File</th><th>Review status</th><th>Uploaded</th></tr></thead><tbody>
            <?php foreach ($documents as $document): ?>
                <tr><td><?= e(ucwords(str_replace('_', ' ', $document['doc_type']))) ?></td><td><a href="/admin/suppliers/<?= (int) $supplier['id'] ?>/documents/<?= (int) $document['id'] ?>" target="_blank" rel="noopener"><?= e($document['original_name'] ?: 'Open document') ?></a></td><td><?= e($document['review_status']) ?></td><td><?= e(date('d M Y', strtotime((string) $document['uploaded_at']))) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section class="ipl-card p-4">
    <h3 class="h6 fw-bold mb-3">Review decision</h3>
    <form method="post" action="/admin/suppliers/<?= (int) $supplier['id'] ?>/status" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4"><label class="form-label" for="status">Status</label><select class="form-select" name="status" id="status" required><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $supplier['status'] === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="reason">Decision note</label><input class="form-control" type="text" id="reason" name="reason" maxlength="255" value="<?= e($supplier['status_reason'] ?? '') ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save decision</button></div>
    </form>
</section>