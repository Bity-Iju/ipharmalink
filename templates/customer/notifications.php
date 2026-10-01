<?php

/**
 * Notification centre — /account/notifications
 *
 * @var \App\Paginator $paginator
 * @var int $unread
 */
$notifications = $paginator->items();

/** Chooses an icon per notification type. */
$icon = static function (string $type): array {
    return match (true) {
        str_starts_with($type, 'order.')      => ['bi-box-seam', 'success'],
        str_starts_with($type, 'payment.')   => ['bi-credit-card', 'info'],
        str_starts_with($type, 'delivery.')  => ['bi-truck', 'info'],
        str_starts_with($type, 'prescription') => ['bi-file-earmark-medical', 'warning'],
        str_starts_with($type, 'pharmacy.')  => ['bi-shop', 'primary'],
        default                              => ['bi-bell', 'secondary'],
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Notifications</h2>
    <?php if ($unread > 0): ?>
        <form method="post" action="/account/notifications/read-all" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-light">Mark all as read (<?= (int) $unread ?>)</button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-bell"></i></div>
        <h3 class="h5 fw-bold">No notifications</h3>
        <p class="mb-0">We will tell you here when your orders are on the move.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notification):
                [$iconClass, $tone] = $icon((string) $notification['type']); ?>
                <div class="list-group-item d-flex gap-3 p-3<?= (int) $notification['is_read'] === 0 ? 'bg-light' : '' ?>">
                    <span class="brand-mark flex-shrink-0" style="width:36px;height:36px;font-size:.9rem">
                        <i class="bi <?= e($iconClass) ?>"></i>
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
                        <div class="d-flex gap-3 mt-1">
                            <?php if (!empty($notification['link'])): ?>
                                <a href="<?= e((string) $notification['link']) ?>" class="small">View</a>
                            <?php endif; ?>
                            <?php if ((int) $notification['is_read'] === 0): ?>
                                <form method="post" action="/account/notifications/<?= (int) $notification['id'] ?>/read" class="m-0">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-link btn-sm p-0 small">Mark as read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'notifications']); ?>
<?php endif; ?>