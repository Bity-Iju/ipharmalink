<?php

/**
 * Admin audit entry detail — /admin/audit-logs/{id}
 *
 * @var array $entry
 */
$decode = static function (?string $json): array {
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
};

$old     = $decode($entry['old_value'] ?? null);
$new     = $decode($entry['new_value'] ?? null);
$changes = array_keys(array_diff_key($new, $old)) ?: array_keys($new);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">
            Audit entry #<?= (int) $entry['id'] ?>
            <code class="small"><?= e((string) $entry['action']) ?></code>
        </h2>
        <p class="text-muted small mb-0">
            <?= e(date('j F Y H:i:s', strtotime((string) $entry['created_at']))) ?>
        </p>
    </div>
    <a href="/admin/audit-logs" class="btn btn-sm btn-light">Back to audit log</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Actor</h3>
            <div class="mb-2">
                <div class="small text-muted">Name</div>
                <div class="fw-semibold"><?= e((string) $entry['actor_name']) ?></div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Role</div>
                <div><?= status_badge((string) $entry['actor_role']) ?></div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">User ID</div>
                <div class="small"><?= $entry['user_id'] !== null ? e((string) $entry['user_id']) : 'System' ?></div>
            </div>
        </div>

        <div class="ipl-card p-3 mb-3">
            <h3 class="h6 fw-bold mb-3">Context</h3>
            <div class="mb-2">
                <div class="small text-muted">Entity</div>
                <div class="small">
                    <?= e((string) ($entry['entity_type'] ?? '—')) ?>
                    <?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?>
                </div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">IP address</div>
                <div class="small"><?= e((string) $entry['ip_address']) ?></div>
            </div>
            <div class="mb-0">
                <div class="small text-muted">User agent</div>
                <div class="small text-muted text-break"><?= e((string) $entry['user_agent']) ?></div>
            </div>
        </div>

        <?php if (!empty($entry['description'])): ?>
            <div class="ipl-card p-3">
                <h3 class="h6 fw-bold mb-2">Description</h3>
                <p class="small text-muted mb-0"><?= e((string) $entry['description']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <?php if ($new === [] && $old === []): ?>
            <div class="ipl-card empty-state">
                <div class="icon"><i class="bi bi-journal-text"></i></div>
                <h3 class="h5 fw-bold">No value diff recorded</h3>
                <p class="mb-0">This entry logs an event rather than a field change.</p>
            </div>
        <?php else: ?>
            <div class="ipl-card">
                <div class="table-responsive">
                    <table class="table ipl-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($changes as $field): ?>
                                <?php
                                $before = $old[$field]['from'] ?? $old[$field] ?? null;
                                $after  = $new[$field]['to'] ?? $new[$field] ?? null;
                                $show   = static fn($v): string => e(is_scalar($v) || $v === null ? (string) ($v ?? '—') : json_encode($v));
                                ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e((string) $field) ?></td>
                                    <td class="small text-muted"><?= $show($before) ?></td>
                                    <td class="small">
                                        <span class="text-success"><?= $show($after) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>