<?php

/**
 * Statistic tile used across every dashboard.
 *
 * @var string $icon    bootstrap-icon name
 * @var string $label
 * @var string|int|float $value
 * @var string|null $hint     small sub-line
 * @var string $tone         primary|success|warning|danger|info
 * @var string|null $link
 */
$icon  = $icon  ?? 'bi-graph-up';
$label = $label ?? '';
$value = $value ?? '0';
$hint  = $hint  ?? null;
$tone  = $tone  ?? 'primary';
$link  = $link  ?? null;

$bg = [
    'primary' => 'var(--ipl-primary-light)',
    'success' => '#dcfce7',
    'warning' => '#fef3c7',
    'danger'  => '#fee2e2',
    'info'    => '#e0e7ff',
][$tone] ?? 'var(--ipl-primary-light)';

$fg = [
    'primary' => 'var(--ipl-primary-dark)',
    'success' => '#15803d',
    'warning' => '#b45309',
    'danger'  => '#b91c1c',
    'info'    => '#4338ca',
][$tone] ?? 'var(--ipl-primary-dark)';
?>
<div class="stat-tile">
    <span class="icon" style="background:<?= $bg ?>;color:<?= $fg ?>">
        <i class="bi <?= e($icon) ?>"></i>
    </span>
    <div class="min-w-0">
        <div class="value"><?= e((string) $value) ?></div>
        <div class="label text-truncate"><?= e($label) ?></div>
        <?php if ($hint !== null): ?>
            <div class="label" style="font-size:.7rem"><?= e($hint) ?></div>
        <?php endif; ?>
    </div>
    <?php if ($link !== null): ?>
        <a href="<?= e($link) ?>" class="btn btn-sm btn-light ms-auto" aria-label="<?= e($label) ?>">
            <i class="bi bi-arrow-right"></i>
        </a>
    <?php endif; ?>
</div>