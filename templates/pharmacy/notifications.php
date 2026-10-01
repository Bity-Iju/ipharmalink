<?php

/**
 * Pharmacy notifications — /pharmacy/notifications
 *
 * @var \App\Paginator $paginator
 * @var int $unread
 */
$notifications = $paginator->items();

$icon = static function (string $type): string {
    return match (true) {
        str_starts_with($type, 'order.')        => 'bi-box-seam',
        str_starts_with($type, 'pharmacy.')    => 'bi-shop',
        str_starts_with($type, 'stock.')       => 'bi-exclamation-triangle',
        str_starts_with($type, 'payout.')      => 'bi-cash-stack',
        str_starts_with($type, 'prescription') => 'bi-file-earmark-medical',
        default                                => 'bi-bell',
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Notifications</h2>
    <?php if ($unread > 0): ?>
        <span class="badge bg-danger"><?= (int) $unread ?> unread</span>
    <?php endif; ?>
</div>

<?php if ($notifications === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-bell"></i></div>
        <h3 class="h5 fw-bold">No notifications</h3>
        <p class="mb-0">New orders and stock alerts will appear here.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item d-flex gap-3 p-3<?= (int) $notification['is_read'] === 0 ? 'bg-light' : '' ?>">
                    <span class="brand-mark flex-shrink-0" style="width:36px;height:36px;font-size:.9rem">
                        <i class="bi <?= e($icon((string) $notification['type'])) ?>"></i>
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between gap-2">
                            <strong class="small"><?= e((string) $notification['title']) ?></strong>
                            <span class="text-muted small flex-shrink-0">
                                <?= e(date('j M, H:i', strtotime((string) $notification['created_at']))) ?>
                            </span>
                        </div>
                        <?php if (!empty($notification['body'])): ?>
                            <div class="small text-muted"><?= e((string) $notification['body']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notification['link'])): ?>
                            <a href="<?= e((string) $notification['link']) ?>" class="small">View</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'notifications']); ?>
<?php endif; ?>