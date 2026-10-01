<?php

/**
 * Storefront header: topbar, logo, search with autocomplete, category menu,
 * account menu, wishlist and cart.
 *
 * @var int|null $cartCount
 * @var int      $unread
 * @var array|null $user
 * @var array    $settings
 */
$user        = $user ?? null;
$cartCount   = $cartCount ?? 0;
$unread      = $unread ?? 0;
$categories  = $categories ?? \App\View::shared()['headerCategories'] ?? [];
$currentPath = \App\Router::currentPath();
$searchTerm  = isset($_GET['q']) && is_string($_GET['q']) ? (string) $_GET['q'] : '';
?>
<div class="ipl-topbar">
    <div class="container d-flex flex-wrap align-items-center gap-3">
        <span class="d-none d-md-inline"><i class="bi bi-truck me-1"></i> Free delivery on qualifying orders</span>
        <span class="d-none d-lg-inline"><i class="bi bi-shield-check me-1"></i> 100% verified pharmacies</span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if (!empty($settings['phone'])): ?>
                <a href="tel:<?= e($settings['phone']) ?>"><i class="bi bi-telephone me-1"></i><?= e($settings['phone']) ?></a>
            <?php endif; ?>
            <a href="/pharmacy/register"><i class="bi bi-shop me-1"></i> Sell on <?= e($settings['name']) ?></a>
        </div>
    </div>
</div>

<header class="ipl-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg py-2">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <span class="brand-mark"><i class="bi bi-capsule-pill"></i></span>
                <span><?= e($settings['name']) ?></span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <!-- Search -->
                <form action="/search" method="get" class="ipl-searchbar order-lg-3 w-100 my-2 my-lg-0 mx-lg-3" role="search">
                    <div class="input-group position-relative">
                        <input type="search" name="q" class="form-control" placeholder="Search medicines, brands, pharmacies…"
                            value="<?= e($searchTerm) ?>"
                            data-search-input autocomplete="off" aria-label="Search products">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Search</span></button>
                        <div class="list-group position-absolute w-100 mt-1 shadow" data-search-results style="z-index:1050"></div>
                    </div>
                </form>

                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        <a class="ipl-nav-link<?= is_active_nav('/products', '/product/', '/deals') ?>" href="/products">
                            <i class="bi bi-grid me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="ipl-nav-link<?= is_active_nav('/pharmacies', '/pharmacy/') ?>" href="/pharmacies">
                            <i class="bi bi-shop-window me-1"></i> Pharmacies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="ipl-nav-link<?= is_active_nav('/categories', '/category/') ?>" href="/categories">
                            <i class="bi bi-list-ul me-1"></i> Categories
                        </a>
                    </li>

                    <?php if ($user !== null): ?>
                        <li class="nav-item">
                            <a class="ipl-nav-link position-relative" href="<?= e(\App\Auth::isSuperAdmin() ? '/admin/dashboard' : (\App\Auth::isPharmacy() ? '/pharmacy/dashboard' : '/account')) ?>"
                                title="My account">
                                <i class="bi bi-person-circle fs-5"></i>
                            </a>
                        </li>
                        <?php if (\App\Auth::isCustomer()): ?>
                            <li class="nav-item">
                                <a class="ipl-nav-link position-relative" href="/account/wishlist" title="Wishlist">
                                    <i class="bi bi-heart fs-5"></i>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="ipl-nav-link position-relative" href="/account/notifications" title="Notifications">
                                    <i class="bi bi-bell fs-5"></i>
                                    <?php if ($unread > 0): ?>
                                        <span class="cart-count"><?= (int) $unread ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item"><a class="ipl-nav-link" href="/login">Sign in</a></li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-primary btn-sm px-3" href="/register">Create account</a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-soft position-relative d-inline-flex align-items-center gap-2" href="/cart">
                            <i class="bi bi-cart3"></i>
                            <span class="d-none d-sm-inline">Cart</span>
                            <span class="cart-count<?= $cartCount > 0 ? '' : ' d-none' ?>" data-cart-count><?= (int) $cartCount ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Category strip -->
        <nav class="d-none d-lg-flex flex-wrap gap-1 pb-2" aria-label="Product categories">
            <a class="chip<?= $currentPath === '/deals' ? ' bg-brand text-white' : '' ?>" href="/deals">
                <i class="bi bi-lightning-charge-fill"></i> Deals
            </a>
            <?php foreach (array_slice($categories, 0, 9) as $category): ?>
                <a class="chip" href="/category/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
            <?php endforeach; ?>
            <a class="chip" href="/categories">View all <i class="bi bi-arrow-right"></i></a>
        </nav>
    </div>
</header>