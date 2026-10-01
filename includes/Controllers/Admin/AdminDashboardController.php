<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Request;
use App\View;

/**
 * Super admin dashboard: platform-wide health, revenue and oversight queues.
 */
final class AdminDashboardController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /admin/dashboard
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $db      = Database::instance();
        $today   = date('Y-m-d');
        $month   = date('Y-m-01');

        // ---- people -------------------------------------------------------
        $users = $db->first(
            "SELECT
                (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE r.name = 'customer' AND u.deleted_at IS NULL) AS customers,
                (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE r.name = 'customer' AND u.created_at >= :newCustomersMonth AND u.deleted_at IS NULL) AS new_customers,
                (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE r.name = 'delivery_personnel' AND u.deleted_at IS NULL) AS riders,
                (SELECT COUNT(*) FROM users WHERE status = 'suspended' AND deleted_at IS NULL) AS suspended",
            ['newCustomersMonth' => $month]
        ) ?? ['customers' => 0, 'new_customers' => 0, 'riders' => 0, 'suspended' => 0];

        // ---- pharmacies ---------------------------------------------------
        $pharmacies = $db->first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'approved'), 0) AS approved,
                    COALESCE(SUM(status = 'pending'), 0) AS pending,
                    COALESCE(SUM(status = 'suspended'), 0) AS suspended,
                    COALESCE(SUM(status = 'rejected'), 0) AS rejected,
                    COALESCE(SUM(CASE WHEN created_at >= :pharmMonth THEN 1 ELSE 0 END), 0) AS new_this_month
             FROM pharmacies WHERE deleted_at IS NULL",
            ['pharmMonth' => $month]
        ) ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'suspended' => 0, 'rejected' => 0, 'new_this_month' => 0];

        // ---- catalogue ----------------------------------------------------
        $catalog = $db->first(
            "SELECT COUNT(*) AS products,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END), 0) AS awaiting_approval,
                    COALESCE(SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                    COALESCE(SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired
             FROM products WHERE deleted_at IS NULL"
        ) ?? ['products' => 0, 'active' => 0, 'awaiting_approval' => 0, 'out_of_stock' => 0, 'expired' => 0];

        // ---- orders -------------------------------------------------------
        $orders = $db->first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = :today THEN 1 ELSE 0 END), 0) AS today,
                    COALESCE(SUM(CASE WHEN created_at >= :month THEN 1 ELSE 0 END), 0) AS month,
                    COALESCE(SUM(status = 'delivered'), 0) AS delivered,
                    COALESCE(SUM(status = 'cancelled'), 0) AS cancelled,
                    COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','refunded','delivered') THEN 1 ELSE 0 END), 0) AS open
             FROM orders",
            ['today' => $today, 'month' => $month]
        ) ?? ['total' => 0, 'today' => 0, 'month' => 0, 'delivered' => 0, 'cancelled' => 0, 'open' => 0];

        // ---- money --------------------------------------------------------
        $finance = $db->first(
            "SELECT COALESCE(SUM(CASE WHEN payment_status = 'successful' THEN total ELSE 0 END), 0) AS gross_revenue,
                    COALESCE(SUM(CASE WHEN DATE(paid_at) = :today AND payment_status = 'successful' THEN total ELSE 0 END), 0) AS today,
                    COALESCE(SUM(CASE WHEN paid_at >= :month AND payment_status = 'successful' THEN total ELSE 0 END), 0) AS month
             FROM orders",
            ['today' => $today, 'month' => $month]
        ) ?? ['gross_revenue' => 0, 'today' => 0, 'month' => 0];

        $commission = $db->first(
            "SELECT COALESCE(SUM(commission_amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'credited' THEN commission_amount ELSE 0 END), 0) AS credited
             FROM commissions"
        ) ?? ['total' => 0, 'pending' => 0, 'credited' => 0];

        $payouts = $db->first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status IN ('requested','approved','processing') THEN amount ELSE 0 END), 0) AS unpaid,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid
             FROM payouts"
        ) ?? ['total' => 0, 'unpaid' => 0, 'paid' => 0];

        $walletLiabilities = (float) ($db->value('SELECT COALESCE(SUM(balance), 0) FROM pharmacy_wallets') ?? 0);

        // ---- charts -------------------------------------------------------
        $daily = $db->all(
            "SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue
             FROM orders
             WHERE created_at >= :since AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE(created_at) ORDER BY day ASC",
            ['since' => date('Y-m-d', strtotime('-29 days'))]
        );

        $monthly = $db->all(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS revenue
             FROM orders
             WHERE created_at >= :since AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            ['since' => date('Y-m-d', strtotime('-11 months'))]
        );

        $signups = $db->all(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM users
             WHERE created_at >= :since AND deleted_at IS NULL
             GROUP BY DATE(created_at) ORDER BY day ASC",
            ['since' => date('Y-m-d', strtotime('-29 days'))]
        );

        // ---- oversight queues ---------------------------------------------
        $pendingPharmacies = $db->all(
            "SELECT id, name, slug, city, state, created_at, pharmacist_name
             FROM pharmacies WHERE status = 'pending' AND deleted_at IS NULL
             ORDER BY created_at ASC LIMIT 6"
        );

        $recentOrders = $db->all(
            "SELECT o.id, o.order_number, o.status, o.payment_status, o.total, o.created_at,
                    u.full_name AS customer_name,
                    (SELECT COUNT(DISTINCT oi.pharmacy_id) FROM order_items oi WHERE oi.order_id = o.id) AS pharmacies
             FROM orders o JOIN users u ON u.id = o.customer_id
             ORDER BY o.id DESC LIMIT 8"
        );

        $lowStockAlerts = $db->all(
            "SELECT p.id, p.name, p.stock_qty, p.min_stock_level, ph.name AS pharmacy_name
             FROM products p JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.stock_qty <= p.min_stock_level
             ORDER BY p.stock_qty ASC LIMIT 8"
        );

        $failedPayments = (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'failed'");
        $unreadMessages = (int) $db->value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
        $openRefunds    = (int) $db->value("SELECT COUNT(*) FROM refunds WHERE status IN ('pending','processing')");

        $this->view('admin/dashboard', [
            'title'   => 'Dashboard',
            'heading' => 'Platform overview',
            'sidebar' => View::capture('admin/partials/sidebar'),
            'users'   => $users,
            'pharmacies' => $pharmacies,
            'catalog' => $catalog,
            'orders'  => $orders,
            'finance' => $finance,
            'commission' => $commission,
            'payouts' => $payouts,
            'walletLiabilities' => $walletLiabilities,
            'charts'  => ['daily' => $daily, 'monthly' => $monthly, 'signups' => $signups],
            'pendingPharmacies' => $pendingPharmacies,
            'recentOrders'     => $recentOrders,
            'lowStock'         => $lowStockAlerts,
            'alerts' => [
                'failedPayments' => $failedPayments,
                'unreadMessages' => $unreadMessages,
                'openRefunds'    => $openRefunds,
            ],
        ], 'layouts/dashboard');
    }
}
