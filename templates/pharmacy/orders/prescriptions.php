<?php

/**
 * Prescription review queue — /pharmacy/prescriptions
 *
 * @var \App\Paginator $paginator
 */
$prescriptions = $paginator->items();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Prescription review</h2>
    <a href="/pharmacy/orders" class="btn btn-sm btn-light">Back to orders</a>
</div>

<div class="alert alert-warning small">
    <i class="bi bi-shield-exclamation me-1"></i>
    Prescription-only medicines must not be dispensed until a licensed pharmacist has reviewed
    the prescription and approved it. Record your decision for every upload.
</div>

<?php if ($prescriptions === []): ?>
    <div class="ipl-card empty-state">
        <div class="icon"><i class="bi bi-file-earmark-medical"></i></div>
        <h3 class="h5 fw-bold">No prescriptions to review</h3>
        <p class="mb-0">Uploads appear here as soon as a customer attaches one at checkout.</p>
    </div>
<?php else: ?>
    <div class="ipl-card">
        <div class="table-responsive">
            <table class="table ipl-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th class="text-end">Review</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                        <tr>
                            <td class="small">
                                <?= e((string) $rx['customer_name']) ?>
                                <div class="text-muted small"><?= e((string) $rx['customer_phone']) ?></div>
                            </td>
                            <td class="small">
                                <?php if (!empty($rx['order_number'])): ?>
                                    <a href="/pharmacy/orders/<?= (int) $rx['order_id'] ?>" class="fw-semibold">
                                        <?= e((string) $rx['order_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime((string) $rx['created_at']))) ?></td>
                            <td><?= status_badge((string) $rx['status']) ?></td>
                            <td class="text-end">
                                <a href="<?= e(upload_url((string) $rx['file_path'])) ?>" target="_blank" rel="noopener"
                                    class="btn btn-sm btn-light" title="View prescription">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>

                        <?php if ($rx['status'] === 'pending'): ?>
                            <tr>
                                <td colspan="5" class="bg-light">
                                    <form method="post" action="/pharmacy/prescriptions/<?= (int) $rx['id'] ?>"
                                        class="d-flex flex-wrap gap-2 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="text" name="review_note" class="form-control form-control-sm flex-grow-1"
                                            style="min-width:220px" placeholder="Review note (required when rejecting)">
                                        <button name="status" value="approved" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg me-1"></i>Approve
                                        </button>
                                        <button name="status" value="rejected" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-lg me-1"></i>Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php elseif (!empty($rx['review_note'])): ?>
                            <tr>
                                <td colspan="5" class="small text-muted fst-italic">
                                    <?= e((string) $rx['review_note']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php \App\View::include('components/pagination', ['paginator' => $paginator, 'label' => 'prescriptions']); ?>
<?php endif; ?>