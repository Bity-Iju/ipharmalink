<?php
/**
 * Flash messages rendered as Bootstrap toasts.
 * Session::pullFlash() returns ['success' => ['…'], 'error' => ['…'], …].
 */
$flashes = \App\Session::pullFlash();
$tones = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'dark'];
?>
<?php if ($flashes !== []): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090">
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
                <div class="toast show align-items-center border-0 text-bg-<?= e($tones[$type] ?? 'dark') ?>" role="alert">
                    <div class="d-flex">
                        <div class="toast-body"><?= e($message) ?></div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>