<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\View;

/**
 * Pharmacy reports: sales, top products, inventory, low stock, expiries and
 * revenue. All figures are scoped to the signed-in pharmacy.
 */
final class ReportController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /pharmacy/reports
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $from = (string) $request->query('from', date('Y-m-01'));
        $to   = (string) $request->query('to', date('Y-m-d'));
        $from = max($from, '2000-01-01');
        $to   = min($to, date('Y-m-d'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $summary = $db->first(
            "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(po.total), 0) AS revenue,
                    COALESCE(SUM(po.platform_fee), 0) AS commission,
                    COALESCE(SUM(po.pharmacy_earnings), 0) AS earnings,
                    COALESCE(AVG(po.total), 0) AS average_order
             FROM pharmacy_orders po
             WHERE po.pharmacy_id = ? AND DATE(po.created_at) BETWEEN ? AND ?
               AND po.status NOT IN ('cancelled','refunded')",
            ['pharmacy_id' => $pharmacyId, 'from' => $from, 'to' => $to]
        ) ?? ['orders' => 0, 'revenue' => 0, 'commission' => 0, 'earnings' => 0, 'average_order' => 0];

        $byStatus = $db->all(
            'SELECT status, COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue
             FROM pharmacy_orders
             WHERE pharmacy_id = ? AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY status ORDER BY total DESC',
            ['pharmacy_id' => $pharmacyId, 'from' => $from, 'to' => $to]
        );

        $this->view('pharmacy/reports/index', [
            'title'     => 'Reports',
            'heading'   => 'Reports',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'summary'   => $summary,
            'byStatus'  => $byStatus,
            'from'      => $from,
            'to'        => $to,
            'products'  => $db->all(
                'SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
                 FROM order_items oi
                 JOIN pharmacy_orders po ON po.order_id = oi.order_id AND po.pharmacy_id = oi.pharmacy_id
                 WHERE oi.pharmacy_id = ? AND DATE(po.created_at) BETWEEN ? AND ?
                   AND po.status NOT IN ("cancelled","refunded")
                 GROUP BY oi.product_name ORDER BY revenue DESC LIMIT 10',
                ['pharmacy_id' => $pharmacyId, 'from' => $from, 'to' => $to]
            ),
            'lowStock'  => $db->all(
                'SELECT name, stock_qty, min_stock_level FROM products
                 WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1 AND stock_qty <= min_stock_level
                 ORDER BY stock_qty ASC LIMIT 10',
                ['pharmacy_id' => $pharmacyId]
            ),
            'expiring'  => $db->all(
                'SELECT name, stock_qty, expiry_date FROM products
                 WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1
                   AND expiry_date IS NOT NULL AND expiry_date <= ?
                 ORDER BY expiry_date ASC LIMIT 10',
                ['pharmacy_id' => $pharmacyId, 'until' => date('Y-m-d', strtotime('+90 days'))]
            ),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/reports/sales
    // -----------------------------------------------------------------------
    public function sales(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $from = (string) $request->query('from', date('Y-m-01'));
        $to   = (string) $request->query('to', date('Y-m-d'));

        $rows = Database::instance()->all(
            "SELECT DATE(po.created_at) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(po.total), 0) AS revenue,
                    COALESCE(SUM(po.pharmacy_earnings), 0) AS earnings
             FROM pharmacy_orders po
             WHERE po.pharmacy_id = ? AND DATE(po.created_at) BETWEEN ? AND ?
               AND po.status NOT IN ('cancelled','refunded')
             GROUP BY DATE(po.created_at) ORDER BY day ASC",
            ['pharmacy_id' => $pharmacyId, 'from' => $from, 'to' => $to]
        );

        $this->view('pharmacy/reports/sales', [
            'title'   => 'Sales report',
            'heading' => 'Sales report',
            'sidebar' => View::capture('pharmacy/partials/sidebar'),
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
            'breadcrumbs' => [['label' => 'Reports', 'url' => '/pharmacy/reports'], ['label' => 'Sales']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/reports/products
    // -----------------------------------------------------------------------
    public function products(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $rows = Database::instance()->all(
            'SELECT oi.product_id, oi.product_name, oi.sku,
                    SUM(oi.quantity) AS units,
                    SUM(oi.line_total) AS revenue,
                    COUNT(DISTINCT po.order_id) AS orders
             FROM order_items oi
             JOIN pharmacy_orders po ON po.order_id = oi.order_id AND po.pharmacy_id = oi.pharmacy_id
             WHERE oi.pharmacy_id = ? AND po.status NOT IN ("cancelled","refunded")
             GROUP BY oi.product_id, oi.product_name, oi.sku
             ORDER BY revenue DESC LIMIT 200',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/reports/products', [
            'title'   => 'Product sales',
            'heading' => 'Product sales report',
            'sidebar' => View::capture('pharmacy/partials/sidebar'),
            'rows'    => $rows,
            'breadcrumbs' => [['label' => 'Reports', 'url' => '/pharmacy/reports'], ['label' => 'Products']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/reports/inventory
    // -----------------------------------------------------------------------
    public function inventory(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $rows = Database::instance()->all(
            'SELECT p.sku, p.name, p.pack_size, p.stock_qty, p.min_stock_level, p.price,
                    p.discount_price, p.batch_number, p.expiry_date,
                    COALESCE(p.stock_qty * COALESCE(NULLIF(p.discount_price, 0), p.price), 0) AS stock_value
             FROM products p
             WHERE p.pharmacy_id = ? AND p.deleted_at IS NULL
             ORDER BY p.name ASC LIMIT 500',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/reports/inventory', [
            'title'   => 'Inventory report',
            'heading' => 'Inventory valuation',
            'sidebar' => View::capture('pharmacy/partials/sidebar'),
            'rows'    => $rows,
            'totalValue' => array_sum(array_map(static fn(array $r): float => (float) $r['stock_value'], $rows)),
            'breadcrumbs' => [['label' => 'Reports', 'url' => '/pharmacy/reports'], ['label' => 'Inventory']],
        ], 'layouts/dashboard');
    }
}
