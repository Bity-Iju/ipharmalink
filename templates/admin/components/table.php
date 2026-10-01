<?php

/**
 * Generic admin list table.
 *
 * Renders a paged result set as a responsive Bootstrap table from a column
 * spec, so the many admin index pages stay short and consistent.
 *
 * @var \App\Paginator $paginator
 * @var list<array{key:string,label:string,type?:string,badge?:string,link?:string,format?:string,truncate?:int}> $columns
 * @var array  $actions   list of ['label','icon','href'|null,'variant','csrf_path'|null,'confirm']
 * @var string $title     optional heading above the table
 * @var string $empty     message when there are no rows
 */
$rows   = $paginator->items();
$format = static function (string $key, array $row, array $column): string {
    $value = $row[$key] ?? null;

    $rendered = match ($column['type'] ?? 'text') {
        'money'   => money((float) $value),
        'compact' => money_compact((float) $value),
        'date'    => $value !== null ? date('j M Y', strtotime((string) $value)) : '—',
        'datetime' => $value !== null ? date('j M Y H:i', strtotime((string) $value)) : '—',
        'number'  => number_format((float) $value),
        'bool'    => (int) $value === 1 ? 'Yes' : 'No',
        'badge'   => status_badge((string) $value),
        default   => (string) ($value ?? '—'),
    };

    if (isset($column['truncate'])) {
        $rendered = str_excerpt($rendered, $column['truncate']);
    }

    return e($rendered);
};
?>
<?php if (!empty($title)): ?>
    <h2 class="h5 fw-bold mb-3"><?= e($title) ?></h2>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-inbox"></i></div>
        <p class="mb-0"><?= e($empty ?? 'Nothing to show here yet.') ?></p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th class="<?= ($column['align'] ?? '') === 'end' ? 'text-end' : '' ?>">
                                <?= e($column['label']) ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if (!empty($actions)): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <td class="<?= ($column['align'] ?? '') === 'end' ? 'text-end' : ($column['class'] ?? 'small') ?>">
                                    <?php if (!empty($column['link'])):
                                        $href = str_replace('{id}', (string) $row['id'], (string) $column['link']); ?>
                                        <a href="<?= e($href) ?>" class="text-reset fw-semibold">
                                            <?= $format($column['key'], $row, $column) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $format($column['key'], $row, $column) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>

                            <?php if (!empty($actions)): ?>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php foreach ($actions as $action):
                                            if (isset($action['when']) && !$action['when']) {
                                                continue;
                                            }
                                            $href = isset($action['href'])
                                                ? str_replace('{id}', (string) $row['id'], (string) $action['href'])
                                                : null; ?>
                                            <?php if ($href !== null): ?>
                                                <a href="<?= e($href) ?>"
                                                    class="btn btn-sm <?= e($action['variant'] ?? 'btn-light') ?>"
                                                    title="<?= e($action['label']) ?>">
                                                    <i class="bi <?= e($action['icon'] ?? 'bi-arrow-right') ?>"></i>
                                                </a>
                                            <?php else: ?>
                                                <form method="post"
                                                    action="<?= e(str_replace('{id}', (string) $row['id'], (string) ($action['path'] ?? ''))) ?>"
                                                    class="m-0"
                                                    <?php if (!empty($action['confirm'])): ?>
                                                    data-confirm="<?= e($action['confirm']) ?>"
                                                    <?php endif; ?>>
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm <?= e($action['variant'] ?? 'btn-light') ?>"
                                                        title="<?= e($action['label']) ?>">
                                                        <i class="bi <?= e($action['icon'] ?? 'bi-check') ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'records']); ?>
<?php endif; ?>