<?php

/**
 * Admin contact inbox — /admin/contact-messages
 *
 * @var \App\Paginator $paginator
 * @var string $status
 */
$statuses = ['' => 'All', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'];
?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($statuses as $value => $label): ?>
        <a href="/admin/contact-messages<?= $value !== '' ? '?status=' . e($value) : '' ?>"
            class="chip<?= $status === $value ? ' bg-brand text-white' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($paginator->isEmpty()): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-envelope"></i></div>
        <h3 class="h5 fw-bold">Inbox is empty</h3>
        <p class="mb-0">Messages sent through the contact form land here.</p>
    </div>
<?php else: ?>
    <div class="d-grid gap-2">
        <?php foreach ($paginator->items() as $message): ?>
            <div class="ipl-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            <?= e((string) $message['subject']) ?>
                            <?= status_badge((string) $message['status']) ?>
                        </h3>
                        <div class="text-muted small">
                            <span class="fw-semibold"><?= e((string) $message['name']) ?></span>
                            · <?= e((string) $message['email']) ?>
                            <?php if (!empty($message['phone'])): ?>
                                · <?= e((string) $message['phone']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end small text-muted">
                        <?= e(date('j M Y H:i', strtotime((string) $message['created_at']))) ?>
                        <?php if (!empty($message['ip_address'])): ?>
                            <div><?= e((string) $message['ip_address']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="small mb-2"><?= nl2br(e((string) $message['message'])) ?></p>

                <div class="d-flex gap-1">
                    <a href="mailto:<?= e((string) $message['email']) ?>" class="btn btn-sm btn-primary" title="Reply by email">
                        <i class="bi bi-reply"></i>
                    </a>
                    <?php foreach (['read', 'replied', 'archived'] as $next): ?>
                        <?php if ((string) $message['status'] !== $next): ?>
                            <form method="post" action="/admin/contact-messages/<?= (int) $message['id'] ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= e($next) ?>">
                                <button class="btn btn-sm btn-light" title="Mark <?= e($next) ?>">
                                    <i class="bi bi-check2"></i> <?= e(ucfirst($next)) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'messages']); ?>
<?php endif; ?>