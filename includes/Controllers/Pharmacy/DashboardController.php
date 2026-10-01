<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Setting;
use App\View;

/**
 * Pharmacy dashboard — the vendor's operational home.
 */
final class DashboardController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /pharmacy/dashboard
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();
        $today      = date('Y-m-d');
        $monthStart = date('Y-m-01');

        // ---- inventory ---------------------------------------------------
        $products = $db->first(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN is_active = 1 AND stock_qty = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                    COALESCE(SUM(CASE WHEN is_active = 1 AND stock_qty > 0 AND stock_qty <= min_stock_level THEN 1 ELSE 0 END), 0) AS low_stock,
                    COALESCE(SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired
             FROM products WHERE pharmacy_id = ? AND deleted_at IS NULL',
            ['pharmacy_id' => $pharmacyId]
        ) ?? ['total' => 0, 'active' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'expired' => 0];

        // ---- orders by status --------------------------------------------
        $statusRows = $db->all(
            'SELECT status, COUNT(*) AS total FROM pharmacy_orders WHERE pharmacy_id = ? GROUP BY status',
            ['pharmacy_id' => $pharmacyId]
        );
        $orders = array_fill_keys([
            'pending_payment',
            'paid',
            'received',
            'processing',
            'preparing',
            'ready_for_pickup',
            'ready_for_delivery',
            'out_for_delivery',
            'delivered',
            'cancelled',
            'refunded',
        ], 0);
        foreach ($statusRows as $row) {
            $orders[(string) $row['status']] = (int) $row['total'];
        }

        $todayOrders = (int) $db->value(
            'SELECT COUNT(*) FROM pharmacy_orders WHERE pharmacy_id = ? AND DATE(created_at) = ?',
            ['pharmacy_id' => $pharmacyId, 'day' => $today]
        );

        // ---- sales (payment state lives on the parent order) -------------
        $sales = $db->first(
            "SELECT COALESCE(SUM(CASE WHEN DATE(po.created_at) = :today THEN po.total ELSE 0 END), 0) AS today,
                    COALESCE(SUM(CASE WHEN po.created_at >= :month THEN po.total ELSE 0 END), 0) AS month,
                    COALESCE(SUM(CASE WHEN o.payment_status = 'successful'
                                       AND po.status NOT IN ('cancelled','refunded')
                                      THEN po.pharmacy_earnings ELSE 0 END), 0) AS earnings,
                    COALESCE(SUM(CASE WHEN po.status = 'delivered' THEN po.total ELSE 0 END), 0) AS lifetime
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             WHERE po.pharmacy_id = :pharm",
            ['today' => $today, 'month' => $monthStart, 'pharm' => $pharmacyId]
        ) ?? ['today' => 0, 'month' => 0, 'earnings' => 0, 'lifetime' => 0];

        // ---- customers ----------------------------------------------------
        // The buyer lives on the parent order, not on the pharmacy slice.
        $customers = (int) $db->value(
            'SELECT COUNT(DISTINCT o.customer_id)
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             WHERE po.pharmacy_id = ?',
            ['pharmacy_id' => $pharmacyId]
        );

        $newCustomers = (int) $db->value(
            'SELECT COUNT(DISTINCT o.customer_id)
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             WHERE po.pharmacy_id = ? AND DATE(po.created_at) >= ?',
            ['pharmacy_id' => $pharmacyId, 'since' => date('Y-m-d', strtotime('-30 days'))]
        );

        // ---- charts: last 14 days of sales & order volume -----------------
        $daily = $db->all(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS revenue
             FROM pharmacy_orders
             WHERE pharmacy_id = ? AND created_at >= ? AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE(created_at) ORDER BY day ASC",
            ['pharmacy_id' => $pharmacyId, 'since' => date('Y-m-d', strtotime('-13 days'))]
        );

        $months = $db->all(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COALESCE(SUM(total), 0) AS revenue
             FROM pharmacy_orders
             WHERE pharmacy_id = ? AND created_at >= ? AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            ['pharmacy_id' => $pharmacyId, 'since' => date('Y-m-d', strtotime('-11 months'))]
        );

        // ---- top products --------------------------------------------------
        $topProducts = $db->all(
            'SELECT oi.product_id, oi.product_name,
                    SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
             FROM order_items oi
             JOIN pharmacy_orders po ON po.order_id = oi.order_id AND po.pharmacy_id = oi.pharmacy_id
             WHERE oi.pharmacy_id = ? AND po.status NOT IN ("cancelled","refunded")
             GROUP BY oi.product_id, oi.product_name
             ORDER BY units DESC LIMIT 6',
            ['pharmacy_id' => $pharmacyId]
        );

        // ---- work queue ---------------------------------------------------
        $newOrders = $db->all(
            "SELECT po.id, po.sub_order_number, po.total, po.status, po.created_at,
                    o.order_number, o.fulfilment_method,
                    u.full_name AS customer_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = po.order_id AND oi.pharmacy_id = po.pharmacy_id) AS item_count
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             JOIN users u ON u.id = o.customer_id
             WHERE po.pharmacy_id = ? AND po.status IN ('paid','received')
             ORDER BY po.id ASC LIMIT 6",
            ['pharmacy_id' => $pharmacyId]
        );

        $lowStockItems = $db->all(
            'SELECT id, name, stock_qty, min_stock_level, expiry_date
             FROM products
             WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1
               AND stock_qty <= min_stock_level
             ORDER BY stock_qty ASC, name ASC LIMIT 8',
            ['pharmacy_id' => $pharmacyId]
        );

        $expiring = $db->all(
            'SELECT id, name, stock_qty, expiry_date FROM products
             WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1
               AND expiry_date IS NOT NULL AND expiry_date <= ?
             ORDER BY expiry_date ASC LIMIT 8',
            ['pharmacy_id' => $pharmacyId, 'until' => date('Y-m-d', strtotime('+' . Setting::getInt('business.expiry_alert_days', 90) . ' days'))]
        );

        $wallet = $db->first('SELECT * FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId])
            ?? ['balance' => 0, 'pending_balance' => 0, 'total_earned' => 0, 'total_paid' => 0];

        $pharmacy = $db->first('SELECT * FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]) ?? [];

        $this->view('pharmacy/dashboard', [
            'title'   => 'Dashboard',
            'heading' => 'Dashboard',
            'sidebar' => View::capture('pharmacy/partials/sidebar', ['pharmacy' => $pharmacy]),
            'stats'   => [
                'products'      => $products,
                'orders'        => $orders,
                'todayOrders'   => $todayOrders,
                'sales'         => $sales,
                'customers'     => $customers,
                'newCustomers'  => $newCustomers,
            ],
            'wallet'  => $wallet,
            'charts'  => ['daily' => $daily, 'months' => $months],
            'topProducts' => $topProducts,
            'newOrders'   => $newOrders,
            'lowStock'    => $lowStockItems,
            'expiring'    => $expiring,
            'pharmacy'    => $pharmacy,
        ], 'layouts/dashboard');
    }
}
