<?php

/**
 * Admin audit log — /admin/audit-logs
 *
 * @var \App\Paginator $paginator
 * @var array $filters
 * @var array $entityTypes
 * @var array $actions
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Audit logs
        <span class="text-muted fw-normal">(<?= number_format($paginator->total()) ?>)</span>
    </h2>
    <a href="/admin/audit-logs" class="btn btn-sm btn-light">Reset filters</a>
</div>

<form method="get" class="ipl-card p-3 mb-3">
    <div class="row g-2 mb-2">
        <div class="col-lg-4">
            <input type="search" name="q" class="form-control" placeholder="Action, description or actor…"
                value="<?= e($filters['q']) ?>">
        </div>
        <div class="col-lg-3">
            <select name="entity_type" class="form-select">
                <option value="">Any entity</option>
                <?php foreach ($entityTypes as $type): ?>
                    <option value="<?= e((string) $type) ?>" <?= $filters['entity_type'] === $type ? ' selected' : '' ?>>
                        <?= e(ucfirst((string) $type)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3">
            <select name="action" class="form-select">
                <option value="">Any action</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e((string) $action) ?>" <?= $filters['action'] === $action ? ' selected' : '' ?>>
                        <?= e((string) $action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-lg-3">
            <label class="form-label small mb-0" for="from">From</label>
            <input type="date" name="from" id="from" class="form-control form-control-sm" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-lg-3">
            <label class="form-label small mb-0" for="to">To</label>
            <input type="date" name="to" id="to" class="form-control form-control-sm" value="<?= e($filters['to']) ?>">
        </div>
    </div>
</form>

<?php if ($paginator->isEmpty()): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-journal-text"></i></div>
        <h3 class="h5 fw-bold">No matching entries</h3>
        <p class="mb-0">Audit entries appear here as users and admins act on the platform.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:70px">#</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>IP</th>
                        <th>When</th>
                        <th class="text-end">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginator->items() as $entry): ?>
                        <tr>
                            <td class="small text-muted"><?= (int) $entry['id'] ?></td>
                            <td class="small">
                                <div class="fw-semibold"><?= e((string) $entry['actor_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= e((string) $entry['actor_role']) ?></div>
                            </td>
                            <td><code class="small"><?= e((string) $entry['action']) ?></code></td>
                            <td class="small text-muted">
                                <?php if (!empty($entry['entity_type'])): ?>
                                    <?= e((string) $entry['entity_type']) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e(str_excerpt((string) $entry['description'], 48)) ?></td>
                            <td class="small text-muted"><?= e((string) $entry['ip_address']) ?></td>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('j M, H:i', strtotime((string) $entry['created_at']))) ?>
                            </td>
                            <td class="text-end">
                                <a href="/admin/audit-logs/<?= (int) $entry['id'] ?>" class="btn btn-sm btn-light">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'entries']); ?>
<?php endif; ?>