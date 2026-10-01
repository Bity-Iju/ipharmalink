<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Session;
use App\View;

/**
 * Pharmacy inventory: stock levels, low-stock and expiry watchlists, the
 * movement ledger, manual adjustments and expired-stock write-offs.
 */
final class InventoryController extends Controller
{
    private InventoryService $inventory;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->inventory = new InventoryService();
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/inventory
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();
        $search     = trim((string) $request->query('q', ''));

        $where  = ['p.pharmacy_id = :pharm', 'p.deleted_at IS NULL'];
        $params = ['pharm' => $pharmacyId];

        if ($search !== '') {
            $where[]      = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
            $like         = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM products p WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT p.id, p.sku, p.name, p.stock_qty, p.min_stock_level, p.batch_number,
                            p.expiry_date, p.is_active, p.pack_size,
                            (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                              ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
                     FROM products p WHERE {$clause}
                     ORDER BY p.stock_qty ASC, p.name ASC
                     LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(25),
            $this->page()
        );

        $summary = $db->first(
            "SELECT COUNT(*) AS products,
                    COALESCE(SUM(stock_qty), 0) AS units,
                    COALESCE(SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                    COALESCE(SUM(CASE WHEN stock_qty > 0 AND stock_qty <= min_stock_level THEN 1 ELSE 0 END), 0) AS low_stock,
                    COALESCE(SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired
             FROM products WHERE pharmacy_id = ? AND deleted_at IS NULL",
            ['pharmacy_id' => $pharmacyId]
        ) ?? ['products' => 0, 'units' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'expired' => 0];

        $this->view('pharmacy/inventory/index', [
            'title'     => 'Inventory',
            'heading'   => 'Inventory',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
            'summary'   => $summary,
            'search'    => $search,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/inventory/low-stock
    // -----------------------------------------------------------------------
    public function lowStock(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $products = Database::instance()->all(
            'SELECT p.id, p.sku, p.name, p.stock_qty, p.min_stock_level, p.pack_size,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p
             WHERE p.pharmacy_id = ? AND p.deleted_at IS NULL AND p.is_active = 1
               AND p.stock_qty <= p.min_stock_level
             ORDER BY (p.stock_qty = 0) DESC, p.stock_qty ASC, p.name ASC
             LIMIT 200',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/inventory/low-stock', [
            'title'    => 'Low stock',
            'heading'  => 'Low stock alerts',
            'sidebar'  => View::capture('pharmacy/partials/sidebar'),
            'products' => $products,
            'breadcrumbs' => [['label' => 'Inventory', 'url' => '/pharmacy/inventory'], ['label' => 'Low stock']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/inventory/expiring
    // -----------------------------------------------------------------------
    public function expiring(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $days       = max(7, $request->queryInt('days', 90));
        $until      = date('Y-m-d', strtotime('+' . $days . ' days'));

        $db       = Database::instance();
        $products = $db->all(
            'SELECT p.id, p.sku, p.name, p.stock_qty, p.batch_number, p.expiry_date, p.pack_size,
                    DATEDIFF(p.expiry_date, CURDATE()) AS days_left,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p
             WHERE p.pharmacy_id = ? AND p.deleted_at IS NULL AND p.is_active = 1
               AND p.expiry_date IS NOT NULL AND p.expiry_date <= ?
             ORDER BY p.expiry_date ASC LIMIT 200',
            ['pharmacy_id' => $pharmacyId, 'until' => $until]
        );

        $expired = array_values(array_filter($products, static fn(array $p): bool => (int) $p['days_left'] < 0));
        $soon    = array_values(array_filter($products, static fn(array $p): bool => (int) $p['days_left'] >= 0));

        $this->view('pharmacy/inventory/expiring', [
            'title'    => 'Expiring stock',
            'heading'  => 'Expiry watchlist',
            'sidebar'  => View::capture('pharmacy/partials/sidebar'),
            'expired'  => $expired,
            'soon'     => $soon,
            'days'     => $days,
            'expiredUnits' => array_sum(array_column($expired, 'stock_qty')),
            'breadcrumbs' => [['label' => 'Inventory', 'url' => '/pharmacy/inventory'], ['label' => 'Expiring']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/inventory/history
    // -----------------------------------------------------------------------
    public function history(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $type       = (string) $request->query('type', '');

        $where  = ['m.pharmacy_id = :pharm'];
        $params = ['pharm' => $pharmacyId];

        if (in_array($type, ['purchase', 'sale', 'return', 'adjustment', 'expiry_writeoff', 'release'], true)) {
            $where[]           = 'm.type = :type';
            $params['type']    = $type;
        }
        if ($request->queryInt('product_id', 0) > 0) {
            $where[]              = 'm.product_id = :prod';
            $params['prod']       = $request->queryInt('product_id', 0);
        }

        $clause = implode(' AND ', $where);

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                "SELECT COUNT(*) FROM inventory_movements m WHERE {$clause}",
                $params
            ),
            static function (Database $db, int $perPage, int $offset) use ($clause, $params): array {
                return $db->all(
                    "SELECT m.*, p.name AS product_name, p.sku, u.full_name AS user_name
                     FROM inventory_movements m
                     LEFT JOIN products p ON p.id = m.product_id
                     LEFT JOIN users u ON u.id = m.user_id
                     WHERE {$clause}
                     ORDER BY m.id DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
            },
            $this->perPage(30),
            $this->page()
        );

        $this->view('pharmacy/inventory/history', [
            'title'     => 'Stock history',
            'heading'   => 'Stock movement history',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'paginator' => $paginator,
            'type'      => $type,
            'productId' => $request->queryInt('product_id', 0),
            'products'  => Database::instance()->all(
                'SELECT id, name FROM products WHERE pharmacy_id = ? AND deleted_at IS NULL ORDER BY name ASC LIMIT 300',
                ['pharmacy_id' => $pharmacyId]
            ),
            'breadcrumbs' => [['label' => 'Inventory', 'url' => '/pharmacy/inventory'], ['label' => 'History']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /pharmacy/inventory/adjust
    // -----------------------------------------------------------------------
    public function adjust(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'product_id' => 'required|integer',
                    'direction'  => 'required|in:in,out',
                    'quantity'   => 'required|integer|min:1',
                    'reason'     => 'required|string|min:3|max:255',
                ],
                $request->all(),
                'pharmacy/inventory/adjust',
                '/pharmacy/inventory/adjust'
            );

            // Ownership is verified before any stock moves.
            $product = Database::instance()->first(
                'SELECT id, name, stock_qty FROM products
                 WHERE id = ? AND pharmacy_id = ? AND deleted_at IS NULL LIMIT 1',
                ['id' => (int) $data['product_id'], 'pharmacy_id' => $pharmacyId]
            );

            if ($product === null) {
                Session::error('That product was not found in your catalogue.');
                Response::redirect('/pharmacy/inventory/adjust');
            }

            $change = $data['direction'] === 'in' ? (int) $data['quantity'] : -(int) $data['quantity'];

            try {
                $result = $this->inventory->adjust(
                    (int) $product['id'],
                    $change,
                    $data['direction'] === 'in' ? InventoryService::TYPE_PURCHASE : InventoryService::TYPE_ADJUSTMENT,
                    (string) $data['reason'],
                    null,
                    'manual',
                    null,
                    (int) Auth::id()
                );
            } catch (\RuntimeException $e) {
                Session::error($e->getMessage());
                Response::redirect('/pharmacy/inventory/adjust');
            }

            (new AuditService())->log('inventory.adjusted', 'product', (int) $product['id'], sprintf(
                '%s stock by %d (%s → %s): %s',
                $data['direction'] === 'in' ? 'Received' : 'Removed',
                (int) $data['quantity'],
                $result['previous'],
                $result['new'],
                (string) $data['reason']
            ));

            Session::success(sprintf(
                'Stock updated: %s now has %d unit(s).',
                (string) $product['name'],
                $result['new']
            ));
            Response::redirect('/pharmacy/inventory');
        }

        $this->view('pharmacy/inventory/adjust', [
            'title'     => 'Adjust stock',
            'heading'   => 'Adjust stock',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'products'  => Database::instance()->all(
                'SELECT id, name, sku, stock_qty, min_stock_level, pack_size FROM products
                 WHERE pharmacy_id = ? AND deleted_at IS NULL AND is_active = 1
                 ORDER BY name ASC LIMIT 400',
                ['pharmacy_id' => $pharmacyId]
            ),
            'breadcrumbs' => [['label' => 'Inventory', 'url' => '/pharmacy/inventory'], ['label' => 'Adjust']],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/inventory/writeoff-expired
    // -----------------------------------------------------------------------
    public function writeOffExpired(Request $request): void
    {
        $count = $this->inventory->writeOffExpired((int) Auth::pharmacyId(), (int) Auth::id());

        if ($count > 0) {
            (new AuditService())->log(
                'inventory.expired_writeoff',
                'product',
                null,
                sprintf('%d expired product(s) written off', $count)
            );
            Session::success(sprintf('%d expired product%s written off and removed from stock.', $count, $count === 1 ? '' : 's'));
        } else {
            Session::info('There is no expired stock to write off.');
        }

        Response::redirect('/pharmacy/inventory/expiring');
    }
}
