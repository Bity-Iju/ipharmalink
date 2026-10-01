<?php

/**
 * Pharmacy customers — /pharmacy/customers
 *
 * @var \App\Paginator $paginator
 */
$customers = $paginator->items();
?>
<h2 class="h5 fw-bold mb-1">My customers</h2>
<p class="text-muted small mb-3">People who have ordered from your pharmacy.</p>

<?php if ($customers === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-people"></i></div>
        <h3 class="h5 fw-bold">No customers yet</h3>
        <p class="mb-0">Your customers will appear here after their first order.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Lifetime value</th>
                        <th>Last order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $customer['full_name']) ?></td>
                            <td class="small text-muted">
                                <?= e((string) $customer['email']) ?>
                                <div><?= e((string) ($customer['phone'] ?? '—')) ?></div>
                            </td>
                            <td class="text-end"><?= (int) $customer['orders'] ?></td>
                            <td class="text-end fw-semibold"><?= money((float) $customer['spent']) ?></td>
                            <td class="small text-muted">
                                <?= $customer['last_order'] !== null
                                    ? e(date('j M Y', strtotime((string) $customer['last_order'])))
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'customers']); ?>
<?php endif; ?>