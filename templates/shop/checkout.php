<?php

/**
 * Multi-step checkout — /checkout
 *
 * @var int   $step   1..5
 * @var string $method 'delivery' | 'pickup'
 * @var array $quote
 * @var array $contents
 * @var array $addresses
 * @var array $contact
 * @var string $note
 * @var bool  $prescriptions
 */
$selectedAddress = (int) \App\Session::get('checkout.address_id', 0);
$stepTitles = [
    1 => 'Your details',
    2 => 'Delivery address',
    3 => 'Delivery method',
    4 => 'Review & place order',
    5 => 'Payment',
];
?>
<div class="container py-4">
    <h1 class="h3 section-title mb-3">Checkout</h1>

    <div class="checkout-steps">
        <?php foreach ($stepTitles as $number => $label): ?>
            <div class="checkout-step <?= $step === $number ? 'is-active' : ($step > $number ? 'is-done' : '') ?>">
                <span class="dot">
                    <?php if ($step > $number): ?>
                        <i class="bi bi-check"></i>
                    <?php else: ?>
                        <?= $number ?>
                    <?php endif; ?>
                </span>
                <span class="d-none d-sm-inline"><?= e($label) ?></span>
            </div>
            <?php if ($number < 5): ?>
                <div class="flex-grow-1 d-none d-md-block" style="border-top:1px dashed var(--ipl-border);margin:.5rem .25rem 0"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($quote['warnings'])): ?>
        <div class="alert alert-warning small">
            <strong>Please note:</strong>
            <ul class="mb-0">
                <?php foreach ($quote['warnings'] as $warning): ?>
                    <li><?= e($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/checkout">
        <?= csrf_field() ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- ============ STEP 1: CONTACT ============ -->
                <?php if ($step === 1): ?>
                    <div class="ipl-card p-4">
                        <h2 class="h5 fw-bold mb-3">Your details</h2>
                        <p class="text-muted small">We use these to contact you about your order.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="full_name">Full name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" required
                                    value="<?= e((string) ($contact['full_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="phone">Phone number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" required
                                    placeholder="0803 000 0000" value="<?= e((string) ($contact['phone'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="email">Email address</label>
                                <input type="email" name="email" id="email" class="form-control" required
                                    value="<?= e((string) ($contact['email'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ============ STEP 2: ADDRESS ============ -->
                <?php if ($step === 2): ?>
                    <div class="ipl-card p-4">
                        <h2 class="h5 fw-bold mb-3">Where should we deliver?</h2>

                        <?php if ($addresses === []): ?>
                            <div class="empty-state py-4">
                                <div class="icon"><i class="bi bi-geo-alt"></i></div>
                                <h3 class="h6 fw-bold">You have no saved addresses</h3>
                                <p class="small">Add one so we know where to bring your order.</p>
                                <a href="/account/addresses" class="btn btn-primary btn-sm">Add a delivery address</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6">
                                        <label class="address-card d-block<?= (int) $address['id'] === $selectedAddress ? ' is-selected' : '' ?>">
                                            <input type="radio" name="address_id" class="d-none"
                                                value="<?= (int) $address['id'] ?>"
                                                data-select-group="address"
                                                data-select-target="checkout_address"
                                                <?= (int) $address['id'] === $selectedAddress ? 'checked' : '' ?>>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="small"><?= e((string) $address['label']) ?></strong>
                                                <?php if ((int) $address['is_default'] === 1): ?>
                                                    <span class="badge bg-success">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?= e((string) $address['recipient_name']) ?><br>
                                                <?= e((string) $address['address_line']) ?><br>
                                                <?= e((string) $address['city']) ?>, <?= e((string) $address['state']) ?><br>
                                                <i class="bi bi-telephone me-1"></i><?= e((string) $address['phone']) ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="checkout_address" data-select-target="checkout_address"
                                value="<?= $selectedAddress > 0 ? $selectedAddress : '' ?>">

                            <a href="/account/addresses" class="btn btn-light btn-sm mt-3">
                                <i class="bi bi-plus-lg me-1"></i> Add another address
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- ============ STEP 3: METHOD ============ -->
                <?php if ($step === 3): ?>
                    <div class="ipl-card p-4">
                        <h2 class="h5 fw-bold mb-3">How would you like to receive your order?</h2>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="method-option d-block<?= $method === 'delivery' ? ' is-selected' : '' ?>">
                                    <input type="radio" name="fulfilment_method" class="d-none" value="delivery"
                                        data-select-group="method"
                                        <?= $method === 'delivery' ? 'checked' : '' ?>>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-truck fs-4 text-brand"></i>
                                        <strong>Home delivery</strong>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Delivered to your address by a verified rider. Fees are set by each pharmacy.
                                    </p>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-option d-block<?= $method === 'pickup' ? ' is-selected' : '' ?>">
                                    <input type="radio" name="fulfilment_method" class="d-none" value="pickup"
                                        data-select-group="method"
                                        <?= $method === 'pickup' ? 'checked' : '' ?>>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-bag-check fs-4 text-brand"></i>
                                        <strong>Pharmacy pickup</strong>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Collect at the pharmacy counter. No delivery fee. We will notify you when ready.
                                    </p>
                                </label>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="note">Note for the pharmacy (optional)</label>
                            <textarea name="note" id="note" rows="3" class="form-control" maxlength="500"
                                placeholder="e.g. Please call when you arrive. Leave with the security guard."><?= e($note) ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ============ STEP 4: REVIEW ============ -->
                <?php if ($step === 4): ?>
                    <div class="ipl-card p-4 mb-3">
                        <h2 class="h5 fw-bold mb-3">Review your order</h2>

                        <?php foreach ($quote['slices'] as $slice): ?>
                            <div class="pb-3 mb-3" style="border-bottom:1px dashed var(--ipl-border)">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?= e((string) $slice['pharmacy_name']) ?></strong>
                                        <?php if ($method === 'pickup'): ?>
                                            <span class="badge bg-success ms-2">Pickup</span>
                                        <?php else: ?>
                                            <span class="badge bg-info ms-2">Delivery</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="fw-semibold"><?= money((float) $slice['total']) ?></span>
                                </div>

                                <?php foreach ($slice['items'] as $item): ?>
                                    <div class="d-flex justify-content-between small py-1">
                                        <span class="text-muted">
                                            <?= (int) $item['quantity'] ?> × <?= e((string) $item['name']) ?>
                                            <?php if ((int) $item['requires_prescription'] === 1): ?>
                                                <i class="bi bi-file-earmark-medical text-warning ms-1" title="Prescription required"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= money((float) $item['line_total']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($quote['requires_prescription']): ?>
                        <div class="ipl-card p-4">
                            <h2 class="h5 fw-bold mb-2">
                                <i class="bi bi-file-earmark-medical text-warning me-1"></i> Prescription required
                            </h2>
                            <p class="small text-muted">
                                One or more items in this order are prescription-only. A licensed pharmacist
                                must review your prescription before the pharmacy can dispense them.
                            </p>
                            <label class="form-label required" for="prescription">Upload your prescription</label>
                            <input type="file" name="prescription" id="prescription" class="form-control" required
                                accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">PDF, JPG or PNG. Maximum 8 MB.</div>
                            <div class="mt-2">
                                <label class="form-label" for="prescription_note">Additional notes</label>
                                <textarea name="prescription_note" id="prescription_note" rows="2" class="form-control"
                                    placeholder="e.g. Prescribed by Dr. Adeyemi at Lagos General"></textarea>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ============ Navigation ============ -->
                <div class="d-flex justify-content-between mt-4">
                    <?php if ($step > 1): ?>
                        <button type="submit" name="action" value="back" class="btn btn-light">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                    <?php else: ?>
                        <a href="/cart" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back to cart</a>
                    <?php endif; ?>

                    <?php if ($step < 4): ?>
                        <button type="submit" name="action" value="next" class="btn btn-primary">
                            Continue <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    <?php elseif ($step === 4): ?>
                        <button type="submit" name="action" value="next" class="btn btn-primary btn-lg">
                            Place order &amp; pay <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= Summary rail ================= -->
            <div class="col-lg-4">
                <div class="ipl-card p-3" style="position:sticky;top:90px">
                    <h2 class="h6 fw-bold mb-3">Order summary</h2>

                    <div class="small text-muted mb-2">
                        <?= (int) $quote['item_count'] ?> item(s) from <?= (int) $quote['pharmacy_count'] ?> pharmacy(ies)
                    </div>

                    <?php foreach ($quote['slices'] as $slice): ?>
                        <div class="d-flex justify-content-between small py-1">
                            <span class="text-muted text-truncate me-2"><?= e((string) $slice['pharmacy_name']) ?></span>
                            <span><?= money((float) $slice['items_subtotal']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <hr class="my-2">

                    <div class="summary-line">
                        <span class="text-muted">Subtotal</span>
                        <span><?= money((float) $quote['subtotal']) ?></span>
                    </div>
                    <?php if ((float) $quote['tax_total'] > 0): ?>
                        <div class="summary-line">
                            <span class="text-muted">Tax</span>
                            <span><?= money((float) $quote['tax_total']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-line">
                        <span class="text-muted">Delivery</span>
                        <span><?= money((float) $quote['delivery_fee']) ?></span>
                    </div>
                    <?php if ((float) $quote['discount_total'] > 0): ?>
                        <div class="summary-line text-success">
                            <span>Discount</span>
                            <span>−<?= money((float) $quote['discount_total']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-line summary-total">
                        <span>Total</span>
                        <span><?= money((float) $quote['total']) ?></span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3 small text-muted">
                        <span><i class="bi bi-shield-lock me-1"></i>Secure checkout</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>