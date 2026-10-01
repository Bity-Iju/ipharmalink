<?php

/**
 * Storefront footer: link columns, trust badges, payment marks, social.
 *
 * @var array $settings
 */
$settings = $settings ?? [];
$pages    = \App\View::shared()['footerPages'] ?? [];
$year     = date('Y');
?>
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="brand-mark"><i class="bi bi-capsule-pill"></i></span>
                    <span class="fw-bold text-white fs-5"><?= e($settings['name'] ?? 'iPharmaLink') ?></span>
                </div>
                <p class="mb-3" style="max-width:38ch">
                    A multi-pharmacy marketplace connecting you to verified pharmacies for genuine medicines,
                    health products and home delivery.
                </p>
                <div class="d-flex gap-3 fs-4">
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Shop</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="/products">All products</a></li>
                    <li><a href="/deals">Today's deals</a></li>
                    <li><a href="/categories">Categories</a></li>
                    <li><a href="/pharmacies">Pharmacies</a></li>
                    <li><a href="/search?q=vitamin">Vitamins</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Account</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="/account/orders">My orders</a></li>
                    <li><a href="/account/wishlist">Wishlist</a></li>
                    <li><a href="/account/addresses">Delivery addresses</a></li>
                    <li><a href="/account/reviews">My reviews</a></li>
                    <li><a href="/login">Sign in</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Company</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="/about">About us</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/faq">Help &amp; FAQ</a></li>
                    <?php foreach ($pages as $page): ?>
                        <li><a href="/page/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Policies</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="/delivery-policy">Delivery policy</a></li>
                    <li><a href="/refund-policy">Refund policy</a></li>
                    <li><a href="/terms">Terms of service</a></li>
                    <li><a href="/privacy">Privacy policy</a></li>
                    <li><a href="/pharmacy-terms">Pharmacy terms</a></li>
                </ul>
            </div>
        </div>

        <hr class="my-4" style="border-color:rgba(255,255,255,.1)">

        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <p class="mb-0 small">&copy; <?= $year ?> <?= e($settings['name'] ?? 'iPharmaLink') ?>. All rights reserved.</p>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-wrap gap-3 justify-content-md-end align-items-center small">
                    <span class="opacity-75">We accept</span>
                    <span class="fw-bold text-white">Paystack</span>
                    <span class="fw-bold text-white">Flutterwave</span>
                    <span class="fw-bold text-white">Bank transfer</span>
                </div>
            </div>
        </div>
    </div>
</footer>