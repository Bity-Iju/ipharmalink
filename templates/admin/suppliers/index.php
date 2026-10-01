<?php
/** @var list<array<string,mixed>> $suppliers */
/** @var string $search */
/** @var string $status */
/** @var array<string,int> $counts */
$labels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'suspended' => 'Suspended',
    'deactivated' => 'Deactivated',
];
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <a href="/admin/suppliers" class="chip<?= $status === '' ? ' bg-brand text-white' : '' ?>">All (<?= (int) ($counts[''] ?? 0) ?>)</a>
    <?php foreach ($labels as $value => $label): ?>
        <a href="/admin/suppliers?status=<?= e($value) ?>" class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>">
            <?= e($label) ?> (<?= (int) ($counts[$value] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2">
        <div class="col-lg-8"><input type="search" name="q" class="form-control" placeholder="Search business, registration number, or email" value="<?= e($search) ?>"></div>
        <div class="col-lg-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach ($labels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2"><button class="btn btn-primary w-100">Filter</button></div>
    </div>
</form>

<div class="ipl-card table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Supplier</th><th>Owner</th><th>Location</th><th>Documents</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><strong><?= e($supplier['name']) ?></strong><div class="small text-muted"><?= e($supplier['registration_number']) ?></div></td>
                    <td><?= e($supplier['owner_name']) ?><div class="small text-muted"><?= e($supplier['email']) ?></div></td>
                    <td><?= e($supplier['city']) ?>, <?= e($supplier['state']) ?></td>
                    <td><?= (int) $supplier['document_count'] ?></td>
                    <td><span class="badge text-bg-<?= $supplier['status'] === 'approved' ? 'success' : (in_array($supplier['status'], ['rejected', 'suspended', 'deactivated'], true) ? 'danger' : 'warning') ?>"><?= e($labels[$supplier['status']] ?? $supplier['status']) ?></span></td>
                    <td class="small text-muted"><?= e(date('d M Y', strtotime((string) $supplier['created_at']))) ?></td>
                    <td><a class="btn btn-sm btn-light" href="/admin/suppliers/<?= (int) $supplier['id'] ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($suppliers === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No supplier registrations matched.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>