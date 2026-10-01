<?php

/**
 * iPharmaLink :: Application routes
 * ---------------------------------------------------------------------------
 * The single source of truth for every URL. Ordering matters: literal routes
 * are matched before parameterised ones by the Router, so this file reads
 * top-to-bottom the way a site map does.
 *
 * Middleware legend:
 *   auth, guest, admin, pharmacy, pharmacy_owner, delivery, customer,
 *   verified, pharmacy.approved, throttle:<bucket>, perm:<permission>
 */

declare(strict_types=1);

use App\Router;
use App\Controllers\Storefront\HomeController;
use App\Controllers\Storefront\CatalogController;
use App\Controllers\Storefront\PharmacyController as PublicPharmacyController;
use App\Controllers\Storefront\PageController;
use App\Controllers\Storefront\HowItWorksController;
use App\Controllers\Storefront\ContactController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Auth\ForgotPasswordController;
use App\Controllers\Auth\VerificationController;
use App\Controllers\Shop\CartController;
use App\Controllers\Shop\CheckoutController;
use App\Controllers\Shop\PaymentController;
use App\Controllers\Shop\WishlistController;
use App\Controllers\Customer\AccountController;
use App\Controllers\Customer\OrderController;
use App\Controllers\Customer\AddressController;
use App\Controllers\Customer\ReviewController;
use App\Controllers\Customer\NotificationController;
use App\Controllers\Pharmacy\DashboardController;
use App\Controllers\Pharmacy\ProductController as PharmacyProductController;
use App\Controllers\Pharmacy\InventoryController;
use App\Controllers\Pharmacy\PharmacyOrderController;
use App\Controllers\Pharmacy\SettingsController;
use App\Controllers\Pharmacy\StaffController;
use App\Controllers\Pharmacy\ReportController;
use App\Controllers\Pharmacy\CustomerController as PharmacyCustomerController;
use App\Controllers\Pharmacy\DeliveryController as PharmacyDeliveryController;
use App\Controllers\Pharmacy\PayoutController;
use App\Controllers\Supplier\SupplierDashboardController;
use App\Controllers\Delivery\DeliveryDashboardController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\PharmacyAdminController;
use App\Controllers\Admin\SupplierAdminController;
use App\Controllers\Admin\ProductAdminController;
use App\Controllers\Admin\CategoryAdminController;
use App\Controllers\Admin\CustomerAdminController;
use App\Controllers\Admin\OrderAdminController;
use App\Controllers\Admin\FinanceAdminController;
use App\Controllers\Admin\CmsAdminController;
use App\Controllers\Admin\ReportAdminController;
use App\Controllers\Admin\DeliveryAdminController;
use App\Controllers\Admin\PrescriptionAdminController;
use App\Controllers\Admin\AuditAdminController;
use App\Controllers\Admin\SettingAdminController;
use App\Controllers\Api\ApiController;

// ===========================================================================
//  PUBLIC STOREFRONT
// ===========================================================================
Router::get('/', [HomeController::class, 'index']);
Router::get('/pharmacies', [PublicPharmacyController::class, 'directory']);
Router::form('/supplier/login', [LoginController::class, 'supplierLogin'], ['guest', 'throttle:login']);
Router::form('/supplier/register', [RegisterController::class, 'supplierRegister'], ['guest', 'throttle:register', 'csrf']);
Router::get('/supplier/dashboard', [SupplierDashboardController::class, 'index'], ['auth', 'supplier', 'supplier.approved']);

// Registered BEFORE /pharmacy/{slug} so "login" and "register" are not
// captured as a pharmacy slug.
Router::form('/pharmacy/login', [LoginController::class, 'pharmacyLogin'], ['guest', 'throttle:login']);
Router::form('/pharmacy/register', [RegisterController::class, 'pharmacyRegister'], ['guest', 'throttle:register']);

// The slug pattern excludes the first segments used by the pharmacy module
// below, so /pharmacy/dashboard is never treated as a storefront slug.
Router::get('/pharmacy/{slug:(?!(?:login|register|dashboard|profile|settings|products|inventory|orders|orders|prescriptions|deliveries|customers|staff|reports|reviews|notifications|wallet|payouts)$)[a-z0-9-]+}', [PublicPharmacyController::class, 'store']);
Router::get('/products', [CatalogController::class, 'index']);
Router::get('/product/{slug:[a-z0-9-]+}', [CatalogController::class, 'show']);
Router::get('/categories', [CatalogController::class, 'categories']);
Router::get('/category/{slug:[a-z0-9-]+}', [CatalogController::class, 'category']);
Router::get('/brand/{slug:[a-z0-9-]+}', [CatalogController::class, 'brand']);
Router::get('/search', [CatalogController::class, 'search'], 'throttle:search');
Router::get('/deals', [CatalogController::class, 'deals']);
Router::get('/sitemap.xml', [HomeController::class, 'sitemap']);
Router::get('/robots.txt', [HomeController::class, 'robots']);

// ---- CMS pages -------------------------------------------------------------
Router::get('/about', [PageController::class, 'about']);
Router::get('/how-it-works', [HowItWorksController::class, 'index']);
Router::get('/terms', [PageController::class, 'terms']);
Router::get('/privacy', [PageController::class, 'privacy']);
Router::get('/delivery-policy', [PageController::class, 'deliveryPolicy']);
Router::get('/refund-policy', [PageController::class, 'refundPolicy']);
Router::get('/pharmacy-terms', [PageController::class, 'pharmacyTerms']);
Router::get('/faq', [PageController::class, 'faq']);
Router::get('/page/{slug:[a-z0-9-]+}', [PageController::class, 'show']);
Router::form('/contact', [ContactController::class, 'handle'], 'throttle:contact');

// ===========================================================================
//  AUTHENTICATION (customer + shared login)
// ===========================================================================
Router::form('/login', [LoginController::class, 'handle'], ['guest', 'throttle:login']);
Router::form('/register', [RegisterController::class, 'handle'], ['guest', 'throttle:register']);
Router::post('/logout', [LoginController::class, 'logout'], 'auth');
Router::form('/forgot-password', [ForgotPasswordController::class, 'handle'], ['guest', 'throttle:password_reset']);
Router::form('/reset-password', [ForgotPasswordController::class, 'reset'], ['guest', 'throttle:password_reset']);
Router::get('/verify-email', [VerificationController::class, 'emailNotice'], 'auth');
Router::form('/verify-email/{token:[a-f0-9]{40,}}', [VerificationController::class, 'verifyEmail'], 'auth');
Router::post('/resend-verification', [VerificationController::class, 'resend'], 'auth');

// ---- Pharmacy (vendor) registration & login -------------------------------
// (GET routes are declared near the top of this file so the storefront slug
//  route cannot swallow them.)

// ---- Admin login -----------------------------------------------------------
Router::form('/admin/login', [LoginController::class, 'adminLogin'], ['guest', 'throttle:login']);

// ===========================================================================
//  CART & CHECKOUT
// ===========================================================================
Router::get('/cart', [CartController::class, 'index']);
Router::post('/cart/add', [CartController::class, 'add'], 'csrf');
Router::post('/cart/update', [CartController::class, 'update'], 'csrf');
Router::post('/cart/remove', [CartController::class, 'remove'], 'csrf');
Router::post('/cart/clear', [CartController::class, 'clear'], 'csrf');
Router::post('/cart/coupon', [CartController::class, 'coupon'], 'csrf');
Router::post('/cart/coupon/remove', [CartController::class, 'removeCoupon'], 'csrf');

Router::get('/checkout', [CheckoutController::class, 'index'], 'auth');
Router::post('/checkout', [CheckoutController::class, 'process'], ['auth', 'csrf']);
Router::get('/checkout/success/{orderNumber:[A-Za-z0-9-]+}', [CheckoutController::class, 'success'], 'auth');

// ---- Payment ---------------------------------------------------------------
Router::get('/payment', [PaymentController::class, 'index'], 'auth');
Router::post('/payment/start', [PaymentController::class, 'start'], ['auth', 'csrf']);
Router::get('/payment/return', [PaymentController::class, 'return'], 'auth');
Router::get('/payment/success', [PaymentController::class, 'success'], 'auth');
Router::get('/payment/failed', [PaymentController::class, 'failed'], 'auth');
Router::post('/payment/verify', [PaymentController::class, 'verify'], ['auth', 'csrf']);
Router::post('/webhooks/paystack', [PaymentController::class, 'webhookPaystack']);
Router::post('/webhooks/flutterwave', [PaymentController::class, 'webhookFlutterwave']);

// ---- Wishlist --------------------------------------------------------------
Router::post('/wishlist/toggle', [WishlistController::class, 'toggle'], ['auth', 'csrf']);
Router::get('/wishlist/move/{productId:\d+}', [WishlistController::class, 'moveToCart'], ['auth', 'csrf']);

// ===========================================================================
//  CUSTOMER ACCOUNT
// ===========================================================================
Router::get('/account', [AccountController::class, 'index'], 'auth');
Router::get('/account/profile', [AccountController::class, 'profile'], 'auth');
Router::post('/account/profile', [AccountController::class, 'updateProfile'], ['auth', 'csrf']);
Router::form('/account/change-password', [AccountController::class, 'changePassword'], ['auth', 'csrf']);
Router::get('/account/addresses', [AddressController::class, 'index'], 'auth');
Router::post('/account/addresses', [AddressController::class, 'store'], ['auth', 'csrf']);
Router::post('/account/addresses/{id:\d+}', [AddressController::class, 'update'], ['auth', 'csrf']);
Router::post('/account/addresses/{id:\d+}/delete', [AddressController::class, 'destroy'], ['auth', 'csrf']);
Router::post('/account/addresses/{id:\d+}/default', [AddressController::class, 'makeDefault'], ['auth', 'csrf']);
Router::get('/account/orders', [OrderController::class, 'index'], 'auth');
Router::get('/account/orders/{id:\d+}', [OrderController::class, 'show'], 'auth');
Router::post('/account/orders/{id:\d+}/cancel', [OrderController::class, 'cancel'], ['auth', 'csrf']);
Router::post('/account/orders/{id:\d+}/reorder', [OrderController::class, 'reorder'], ['auth', 'csrf']);
Router::get('/account/orders/{id:\d+}/receipt', [OrderController::class, 'receipt'], 'auth');
Router::post('/account/orders/{id:\d+}/confirm-delivery', [OrderController::class, 'confirmDelivery'], ['auth', 'csrf']);
Router::get('/account/orders/{id:\d+}/track', [OrderController::class, 'track'], 'auth');
Router::get('/account/payments', [\App\Controllers\Customer\PaymentController::class, 'index'], ['auth', 'customer']);
Router::get('/account/wishlist', [WishlistController::class, 'index'], 'auth');
Router::get('/account/reviews', [ReviewController::class, 'index'], 'auth');
Router::form('/account/reviews/create', [ReviewController::class, 'create'], ['auth', 'csrf']);
Router::get('/account/notifications', [NotificationController::class, 'index'], 'auth');
Router::post('/account/notifications/{id:\d+}/read', [NotificationController::class, 'read'], ['auth', 'csrf']);
Router::post('/account/notifications/read-all', [NotificationController::class, 'readAll'], ['auth', 'csrf']);

// ===========================================================================
//  PHARMACY / VENDOR MODULE
// ===========================================================================
Router::get('/pharmacy/dashboard', [DashboardController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/profile', [SettingsController::class, 'profile'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/profile', [SettingsController::class, 'updateProfile'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::form('/pharmacy/settings', [SettingsController::class, 'settings'], ['auth', 'pharmacy_owner', 'pharmacy.approved', 'csrf']);

Router::get('/pharmacy/products', [PharmacyProductController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/products/create', [PharmacyProductController::class, 'create'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/products', [PharmacyProductController::class, 'store'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::get('/pharmacy/products/edit/{id:\d+}', [PharmacyProductController::class, 'edit'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/products/{id:\d+}', [PharmacyProductController::class, 'update'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/products/{id:\d+}/delete', [PharmacyProductController::class, 'destroy'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/products/{id:\d+}/duplicate', [PharmacyProductController::class, 'duplicate'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/products/{id:\d+}/toggle', [PharmacyProductController::class, 'toggle'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/products/bulk', [PharmacyProductController::class, 'bulk'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);

Router::get('/pharmacy/inventory', [InventoryController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/inventory/low-stock', [InventoryController::class, 'lowStock'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/inventory/expiring', [InventoryController::class, 'expiring'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/inventory/history', [InventoryController::class, 'history'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::form('/pharmacy/inventory/adjust', [InventoryController::class, 'adjust'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/inventory/writeoff-expired', [InventoryController::class, 'writeOffExpired'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);

Router::get('/pharmacy/orders', [PharmacyOrderController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/new', [PharmacyOrderController::class, 'newOrders'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/processing', [PharmacyOrderController::class, 'processing'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/ready', [PharmacyOrderController::class, 'ready'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/delivered', [PharmacyOrderController::class, 'delivered'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/cancelled', [PharmacyOrderController::class, 'cancelled'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/orders/{id:\d+}', [PharmacyOrderController::class, 'show'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/orders/{id:\d+}/action', [PharmacyOrderController::class, 'act'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::get('/pharmacy/prescriptions', [PharmacyOrderController::class, 'prescriptions'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/prescriptions/{id:\d+}', [PharmacyOrderController::class, 'reviewPrescription'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);

Router::get('/pharmacy/deliveries', [PharmacyDeliveryController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::post('/pharmacy/deliveries/{id:\d+}/assign', [PharmacyDeliveryController::class, 'assign'], ['auth', 'pharmacy', 'pharmacy.approved', 'csrf']);
Router::get('/pharmacy/customers', [PharmacyCustomerController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/staff', [StaffController::class, 'index'], ['auth', 'pharmacy_owner', 'pharmacy.approved']);
Router::post('/pharmacy/staff', [StaffController::class, 'store'], ['auth', 'pharmacy_owner', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/staff/{id:\d+}', [StaffController::class, 'update'], ['auth', 'pharmacy_owner', 'pharmacy.approved', 'csrf']);
Router::post('/pharmacy/staff/{id:\d+}/delete', [StaffController::class, 'destroy'], ['auth', 'pharmacy_owner', 'pharmacy.approved', 'csrf']);
Router::get('/pharmacy/reports', [ReportController::class, 'index'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/reports/sales', [ReportController::class, 'sales'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/reports/products', [ReportController::class, 'products'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/reports/inventory', [ReportController::class, 'inventory'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/reviews', [PharmacyCustomerController::class, 'reviews'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/notifications', [PharmacyCustomerController::class, 'notifications'], ['auth', 'pharmacy', 'pharmacy.approved']);
Router::get('/pharmacy/payouts', [PayoutController::class, 'index'], ['auth', 'pharmacy_owner', 'pharmacy.approved']);
Router::post('/pharmacy/payouts', [PayoutController::class, 'request'], ['auth', 'pharmacy_owner', 'pharmacy.approved', 'csrf']);
Router::get('/pharmacy/wallet', [PayoutController::class, 'wallet'], ['auth', 'pharmacy', 'pharmacy.approved']);

// ===========================================================================
//  DELIVERY PERSONNEL MODULE
// ===========================================================================
Router::get('/delivery/dashboard', [DeliveryDashboardController::class, 'index'], ['auth', 'delivery']);
Router::get('/delivery/orders', [DeliveryDashboardController::class, 'orders'], ['auth', 'delivery']);
Router::get('/delivery/orders/{id:\d+}', [DeliveryDashboardController::class, 'show'], ['auth', 'delivery']);
Router::post('/delivery/orders/{id:\d+}/status', [DeliveryDashboardController::class, 'updateStatus'], ['auth', 'delivery', 'csrf']);
Router::get('/delivery/history', [DeliveryDashboardController::class, 'history'], ['auth', 'delivery']);
Router::form('/delivery/profile', [DeliveryDashboardController::class, 'profile'], ['auth', 'delivery', 'csrf']);

// ===========================================================================
//  SUPER ADMIN MODULE
// ===========================================================================
Router::get('/admin/dashboard', [AdminDashboardController::class, 'index'], ['auth', 'admin']);

Router::get('/admin/suppliers', [SupplierAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/suppliers/pending', [SupplierAdminController::class, 'pending'], ['auth', 'admin']);
Router::get('/admin/suppliers/{id:\d+}/documents/{documentId:\d+}', [SupplierAdminController::class, 'document'], ['auth', 'admin']);
Router::get('/admin/suppliers/{id:\d+}', [SupplierAdminController::class, 'show'], ['auth', 'admin']);
Router::post('/admin/suppliers/{id:\d+}/status', [SupplierAdminController::class, 'updateStatus'], ['auth', 'admin', 'csrf']);

Router::get('/admin/pharmacies', [PharmacyAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/pharmacies/pending', [PharmacyAdminController::class, 'pending'], ['auth', 'admin']);
Router::get('/admin/pharmacies/{id:\d+}', [PharmacyAdminController::class, 'show'], ['auth', 'admin']);
Router::post('/admin/pharmacies/{id:\d+}/status', [PharmacyAdminController::class, 'updateStatus'], ['auth', 'admin', 'csrf']);
Router::post('/admin/pharmacies/{id:\d+}', [PharmacyAdminController::class, 'update'], ['auth', 'admin', 'csrf']);
Router::get('/admin/pharmacies/{id:\d+}/products', [PharmacyAdminController::class, 'products'], ['auth', 'admin']);
Router::get('/admin/pharmacies/{id:\d+}/orders', [PharmacyAdminController::class, 'orders'], ['auth', 'admin']);
Router::get('/admin/pharmacies/{id:\d+}/sales', [PharmacyAdminController::class, 'sales'], ['auth', 'admin']);
Router::get('/admin/pharmacies/{id:\d+}/logs', [PharmacyAdminController::class, 'logs'], ['auth', 'admin']);

Router::get('/admin/products', [ProductAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/products/{id:\d+}', [ProductAdminController::class, 'show'], ['auth', 'admin']);
Router::post('/admin/products/{id:\d+}', [ProductAdminController::class, 'update'], ['auth', 'admin', 'csrf']);
Router::post('/admin/products/{id:\d+}/delete', [ProductAdminController::class, 'destroy'], ['auth', 'admin', 'csrf']);
Router::get('/admin/categories', [CategoryAdminController::class, 'categories'], ['auth', 'admin']);
Router::post('/admin/categories', [CategoryAdminController::class, 'storeCategory'], ['auth', 'admin', 'csrf']);
Router::post('/admin/categories/{id:\d+}', [CategoryAdminController::class, 'updateCategory'], ['auth', 'admin', 'csrf']);
Router::post('/admin/categories/{id:\d+}/delete', [CategoryAdminController::class, 'destroyCategory'], ['auth', 'admin', 'csrf']);
Router::get('/admin/brands', [CategoryAdminController::class, 'brands'], ['auth', 'admin']);
Router::post('/admin/brands', [CategoryAdminController::class, 'storeBrand'], ['auth', 'admin', 'csrf']);
Router::post('/admin/brands/{id:\d+}', [CategoryAdminController::class, 'updateBrand'], ['auth', 'admin', 'csrf']);
Router::post('/admin/brands/{id:\d+}/delete', [CategoryAdminController::class, 'destroyBrand'], ['auth', 'admin', 'csrf']);

Router::get('/admin/customers', [CustomerAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/customers/{id:\d+}', [CustomerAdminController::class, 'show'], ['auth', 'admin']);
Router::post('/admin/customers/{id:\d+}/status', [CustomerAdminController::class, 'updateStatus'], ['auth', 'admin', 'csrf']);
Router::get('/admin/staff', [CustomerAdminController::class, 'staff'], ['auth', 'admin']);
Router::get('/admin/delivery-personnel', [CustomerAdminController::class, 'deliveryPersonnel'], ['auth', 'admin']);
Router::form('/admin/delivery-personnel/create', [CustomerAdminController::class, 'createDeliveryPersonnel'], ['auth', 'admin', 'csrf']);

Router::get('/admin/orders', [OrderAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/orders/pending', [OrderAdminController::class, 'pending'], ['auth', 'admin']);
Router::get('/admin/orders/processing', [OrderAdminController::class, 'processing'], ['auth', 'admin']);
Router::get('/admin/orders/delivered', [OrderAdminController::class, 'delivered'], ['auth', 'admin']);
Router::get('/admin/orders/cancelled', [OrderAdminController::class, 'cancelled'], ['auth', 'admin']);
Router::get('/admin/orders/{id:\d+}', [OrderAdminController::class, 'show'], ['auth', 'admin']);

Router::get('/admin/payments', [FinanceAdminController::class, 'payments'], ['auth', 'admin']);
Router::get('/admin/refunds', [FinanceAdminController::class, 'refunds'], ['auth', 'admin']);
Router::form('/admin/refunds/create', [FinanceAdminController::class, 'createRefund'], ['auth', 'admin', 'csrf']);
Router::post('/admin/refunds/create', [FinanceAdminController::class, 'storeRefund'], ['auth', 'admin', 'csrf']);
Router::get('/admin/commissions', [FinanceAdminController::class, 'commissions'], ['auth', 'admin']);
Router::get('/admin/payouts', [FinanceAdminController::class, 'payouts'], ['auth', 'admin']);
Router::post('/admin/payouts/{id:\d+}/status', [FinanceAdminController::class, 'updatePayout'], ['auth', 'admin', 'csrf']);
Router::get('/admin/wallets', [FinanceAdminController::class, 'wallets'], ['auth', 'admin']);
Router::get('/admin/coupons', [FinanceAdminController::class, 'coupons'], ['auth', 'admin']);
Router::post('/admin/coupons', [FinanceAdminController::class, 'storeCoupon'], ['auth', 'admin', 'csrf']);
Router::post('/admin/coupons/{id:\d+}', [FinanceAdminController::class, 'updateCoupon'], ['auth', 'admin', 'csrf']);
Router::post('/admin/coupons/{id:\d+}/delete', [FinanceAdminController::class, 'destroyCoupon'], ['auth', 'admin', 'csrf']);

Router::get('/admin/deliveries', [DeliveryAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/prescriptions', [PrescriptionAdminController::class, 'index'], ['auth', 'admin']);

Router::get('/admin/reports', [ReportAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/reports/sales', [ReportAdminController::class, 'sales'], ['auth', 'admin']);
Router::get('/admin/reports/orders', [ReportAdminController::class, 'orders'], ['auth', 'admin']);
Router::get('/admin/reports/pharmacies', [ReportAdminController::class, 'pharmacies'], ['auth', 'admin']);
Router::get('/admin/reports/customers', [ReportAdminController::class, 'customers'], ['auth', 'admin']);
Router::get('/admin/reports/products', [ReportAdminController::class, 'products'], ['auth', 'admin']);
Router::get('/admin/reports/commissions', [ReportAdminController::class, 'commissions'], ['auth', 'admin']);
Router::get('/admin/reports/payments', [ReportAdminController::class, 'payments'], ['auth', 'admin']);
Router::get('/admin/reports/deliveries', [ReportAdminController::class, 'deliveries'], ['auth', 'admin']);
Router::get('/admin/reports/refunds', [ReportAdminController::class, 'refunds'], ['auth', 'admin']);
Router::get('/admin/reports/inventory', [ReportAdminController::class, 'inventory'], ['auth', 'admin']);
Router::get('/admin/reports/export/{report:[a-z-]+}', [ReportAdminController::class, 'export'], 'auth:admin');

Router::get('/admin/reviews', [CmsAdminController::class, 'reviews'], ['auth', 'admin']);
Router::post('/admin/reviews/{id:\d+}', [CmsAdminController::class, 'moderateReview'], ['auth', 'admin', 'csrf']);
Router::get('/admin/banners', [CmsAdminController::class, 'banners'], ['auth', 'admin']);
Router::post('/admin/banners', [CmsAdminController::class, 'storeBanner'], ['auth', 'admin', 'csrf']);
Router::post('/admin/banners/{id:\d+}', [CmsAdminController::class, 'updateBanner'], ['auth', 'admin', 'csrf']);
Router::post('/admin/banners/{id:\d+}/delete', [CmsAdminController::class, 'destroyBanner'], ['auth', 'admin', 'csrf']);
Router::get('/admin/pages', [CmsAdminController::class, 'pages'], ['auth', 'admin']);
Router::form('/admin/pages/create', [CmsAdminController::class, 'createPage'], ['auth', 'admin', 'csrf']);
Router::post('/admin/pages/create', [CmsAdminController::class, 'storePage'], ['auth', 'admin', 'csrf']);
Router::get('/admin/pages/{id:\d+}', [CmsAdminController::class, 'editPage'], ['auth', 'admin']);
Router::post('/admin/pages/{id:\d+}', [CmsAdminController::class, 'updatePage'], ['auth', 'admin', 'csrf']);
Router::post('/admin/pages/{id:\d+}/delete', [CmsAdminController::class, 'destroyPage'], ['auth', 'admin', 'csrf']);
Router::get('/admin/faqs', [CmsAdminController::class, 'faqs'], ['auth', 'admin']);
Router::post('/admin/faqs', [CmsAdminController::class, 'storeFaq'], ['auth', 'admin', 'csrf']);
Router::post('/admin/faqs/{id:\d+}', [CmsAdminController::class, 'updateFaq'], ['auth', 'admin', 'csrf']);
Router::post('/admin/faqs/{id:\d+}/delete', [CmsAdminController::class, 'destroyFaq'], ['auth', 'admin', 'csrf']);
Router::get('/admin/contact-messages', [CmsAdminController::class, 'contactMessages'], ['auth', 'admin']);
Router::post('/admin/contact-messages/{id:\d+}', [CmsAdminController::class, 'updateMessage'], ['auth', 'admin', 'csrf']);
Router::get('/admin/notifications', [CmsAdminController::class, 'notifications'], ['auth', 'admin']);

Router::get('/admin/audit-logs', [AuditAdminController::class, 'index'], ['auth', 'admin']);
Router::get('/admin/audit-logs/{id:\d+}', [AuditAdminController::class, 'show'], ['auth', 'admin']);

Router::form('/admin/settings', [SettingAdminController::class, 'general'], ['auth', 'admin', 'csrf']);
Router::form('/admin/payment-settings', [SettingAdminController::class, 'payment'], ['auth', 'admin', 'csrf']);
Router::form('/admin/delivery-settings', [SettingAdminController::class, 'delivery'], ['auth', 'admin', 'csrf']);
Router::form('/admin/email-settings', [SettingAdminController::class, 'email'], ['auth', 'admin', 'csrf']);
Router::form('/admin/security-settings', [SettingAdminController::class, 'security'], ['auth', 'admin', 'csrf']);
Router::post('/admin/sms-settings', [SettingAdminController::class, 'sms'], ['auth', 'admin', 'csrf']);
Router::post('/admin/gateways/{code}', [SettingAdminController::class, 'toggleGateway'], ['auth', 'admin', 'csrf']);

// ===========================================================================
//  REST-STYLE API (used by the storefront's fetch() calls and future apps)
// ===========================================================================
Router::post('/api/cart/add', [ApiController::class, 'cartAdd'], 'throttle:api');
Router::post('/api/cart/update', [ApiController::class, 'cartUpdate'], 'throttle:api');
Router::post('/api/cart/remove', [ApiController::class, 'cartRemove'], 'throttle:api');
Router::get('/api/cart', [ApiController::class, 'cart'], 'throttle:api');
Router::get('/api/search/suggest', [ApiController::class, 'searchSuggest'], 'throttle:search');
Router::get('/api/products', [ApiController::class, 'products'], 'throttle:api');
Router::get('/api/products/{slug:[a-z0-9-]+}', [ApiController::class, 'product'], 'throttle:api');
Router::get('/api/pharmacies', [ApiController::class, 'pharmacies'], 'throttle:api');
Router::get('/api/categories', [ApiController::class, 'categories'], 'throttle:api');
Router::get('/api/notifications', [ApiController::class, 'notifications'], ['auth', 'throttle:api']);
Router::get('/api/orders', [ApiController::class, 'orders'], ['auth', 'throttle:api']);
Router::get('/api/orders/{id:\d+}', [ApiController::class, 'order'], ['auth', 'throttle:api']);
Router::get('/api/addresses', [ApiController::class, 'addresses'], ['auth', 'throttle:api']);
Router::get('/api/me', [ApiController::class, 'me'], ['auth', 'throttle:api']);
