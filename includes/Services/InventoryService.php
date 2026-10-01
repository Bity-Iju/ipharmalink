<?php

/**
 * iPharmaLink :: Inventory service
 * ---------------------------------------------------------------------------
 * Every stock change in the platform goes through adjust(). It:
 *   - locks the product row (SELECT … FOR UPDATE) inside the caller's
 *     transaction so concurrent checkouts cannot oversell
 *   - writes an immutable movement row with previous/new quantities
 *   - updates the denormalised products.stock_qty counter
 *   - updates batch quantities when a batch is supplied
 *   - fires low-stock and expiry notifications
 *
 * No other service may write stock_qty directly.
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\Setting;

final class InventoryService
{
    public const TYPE_PURCHASE    = 'purchase';
    public const TYPE_SALE        = 'sale';
    public const TYPE_RETURN      = 'return';
    public const TYPE_ADJUSTMENT  = 'adjustment';
    public const TYPE_EXPIRY      = 'expiry_writeoff';
    public const TYPE_RELEASE     = 'release';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Apply a signed stock change and record the movement.
     *
     * @param  int  $changeQty  positive = stock in, negative = stock out
     * @return array{previous:int,new:int,change:int}
     */
    public function adjust(
        int $productId,
        int $changeQty,
        string $type = self::TYPE_ADJUSTMENT,
        ?string $reason = null,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): array {
        $db = $this->db;

        // Row lock: serialises concurrent orders for the same product.
        $product = $db->first(
            'SELECT id, pharmacy_id, stock_qty, min_stock_level, name, expiry_date
             FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
            ['id' => $productId]
        );
        if ($product === null) {
            throw new \RuntimeException('Cannot adjust stock for a product that does not exist.');
        }

        $previous = (int) $product['stock_qty'];
        $new      = $previous + $changeQty;

        // Never allow negative stock — an oversell is a bug, not a feature.
        if ($new < 0) {
            throw new \RuntimeException(sprintf(
                'Insufficient stock for "%s": %d available, %d requested.',
                (string) $product['name'],
                $previous,
                abs($changeQty)
            ));
        }

        $db->update('products', ['stock_qty' => $new], 'id = ?', ['id' => $productId]);

        if ($batchId !== null) {
            $this->applyToBatch($batchId, $changeQty, $productId);
        }

        $db->insert('inventory_movements', [
            'product_id'     => $productId,
            'pharmacy_id'    => (int) $product['pharmacy_id'],
            'batch_id'       => $batchId,
            'type'           => $type,
            'previous_qty'   => $previous,
            'change_qty'     => $changeQty,
            'new_qty'        => $new,
            'reason'         => $reason,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'user_id'        => $userId ?? Auth::id(),
        ]);

        (new InventoryAlertService($db))->syncProductById($productId);

        return ['previous' => $previous, 'new' => $new, 'change' => $changeQty];
    }

    /**
     * Set stock to an absolute value (bulk edit / stock count).
     *
     * @return array{previous:int,new:int,change:int}
     */
    public function setStock(int $productId, int $targetQty, string $reason = 'Stock count correction', ?int $userId = null): array
    {
        $current = (int) ($this->db->value('SELECT stock_qty FROM products WHERE id = ?', ['id' => $productId]) ?? 0);
        return $this->adjust($productId, $targetQty - $current, self::TYPE_ADJUSTMENT, $reason, null, 'stock_count', null, $userId);
    }

    /** Add received stock, creating a batch when one is supplied. */
    public function receiveStock(
        int $productId,
        int $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $manufacturingDate = null,
        float $costPrice = 0.0,
        ?int $userId = null
    ): array {
        $batchId = null;

        if ($batchNumber !== null && $batchNumber !== '') {
            $existing = $this->db->first(
                'SELECT id FROM product_batches WHERE product_id = ? AND batch_number = ? LIMIT 1',
                ['product_id' => $productId, 'batch_number' => $batchNumber]
            );
            if ($existing !== null) {
                $batchId = (int) $existing['id'];
            } else {
                $batchId = $this->db->insert('product_batches', [
                    'product_id'        => $productId,
                    'batch_number'      => $batchNumber,
                    'expiry_date'       => $expiryDate !== '' ? $expiryDate : null,
                    'manufacturing_date' => $manufacturingDate !== '' ? $manufacturingDate : null,
                    'quantity'          => 0,
                    'cost_price'        => $costPrice,
                ]);
            }
        }

        $name = (string) $this->db->value('SELECT name FROM products WHERE id = ?', ['id' => $productId]);

        return $this->adjust(
            $productId,
            $quantity,
            self::TYPE_PURCHASE,
            'Stock received' . ($batchNumber !== null && $batchNumber !== '' ? " — batch {$batchNumber}" : ''),
            $batchId,
            'purchase',
            null,
            $userId
        );
    }

    /**
     * Allocate stock using First-Expired-First-Out across batches.
     *
     * @return list<array{batch_id:int,quantity:int}>
     */
    public function allocateBatches(int $productId, int $quantity): array
    {
        $batches = $this->db->all(
            'SELECT id, quantity FROM product_batches
             WHERE product_id = ? AND quantity > 0
               AND (expiry_date IS NULL OR expiry_date >= CURDATE())
             ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC
             FOR UPDATE',
            ['product_id' => $productId]
        );

        $allocations = [];
        $remaining   = $quantity;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take  = min((int) $batch['quantity'], $remaining);
            $allocations[] = ['batch_id' => (int) $batch['id'], 'quantity' => $take];
            $remaining -= $take;
        }
        return $allocations;
    }

    // -----------------------------------------------------------------------
    //  Reporting
    // -----------------------------------------------------------------------

    /**
     * Inventory summary for a pharmacy dashboard.
     *
     * @return array{total:int,active:int,out_of_stock:int,low_stock:int,expiring:int,expired:int,stock_value:float}
     */
    public function summary(?int $pharmacyId = null): array
    {
        $where  = $pharmacyId !== null ? ' AND pharmacy_id = ' . (int) $pharmacyId : '';
        $expiryDays = Setting::getInt('system.expiry_alert_days', 90);
        $defaultMin  = Setting::getInt('system.low_stock_threshold_default', 10);

        $row = $this->db->first(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN stock_qty > 0 AND stock_qty <= LEAST(min_stock_level, :min1) THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date >= CURDATE()
                          AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY) THEN 1 ELSE 0 END) AS expiring,
                SUM(stock_qty * price) AS stock_value
             FROM products
             WHERE deleted_at IS NULL AND is_visible = 1{$where}",
            ['min1' => $defaultMin, 'days' => $expiryDays]
        ) ?: [];

        return [
            'total'        => (int) ($row['total'] ?? 0),
            'active'       => (int) ($row['active'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
            'low_stock'    => (int) ($row['low_stock'] ?? 0),
            'expired'      => (int) ($row['expired'] ?? 0),
            'expiring'     => (int) ($row['expiring'] ?? 0),
            'stock_value'  => round((float) ($row['stock_value'] ?? 0), 2),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function movements(int $productId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->all(
            "SELECT im.*, u.full_name AS user_name
             FROM inventory_movements im
             LEFT JOIN users u ON u.id = im.user_id
             WHERE im.product_id = ?
             ORDER BY im.id DESC LIMIT {$limit}",
            ['product_id' => $productId]
        );
    }

    /**
     * Write off expired stock across a pharmacy (or the whole platform).
     *
     * @return int number of products written off
     */
    public function writeOffExpired(?int $pharmacyId = null, ?int $userId = null): int
    {
        $params = [];
        $where  = 'deleted_at IS NULL AND expiry_date IS NOT NULL AND expiry_date < CURDATE() AND stock_qty > 0';
        if ($pharmacyId !== null) {
            $where .= ' AND pharmacy_id = :pharmacy_id';
            $params['pharmacy_id'] = $pharmacyId;
        }

        $products = $this->db->all(
            "SELECT id, stock_qty, expiry_date, name FROM products WHERE {$where}",
            $params
        );

        $writtenOff = 0;
        foreach ($products as $product) {
            $this->adjust(
                (int) $product['id'],
                - ((int) $product['stock_qty']),
                self::TYPE_EXPIRY,
                'Expired on ' . $product['expiry_date'],
                null,
                'expiry',
                null,
                $userId
            );
            $writtenOff++;
        }
        return $writtenOff;
    }

    // -----------------------------------------------------------------------

    private function applyToBatch(int $batchId, int $changeQty, int $productId): void
    {
        $batch = $this->db->first(
            'SELECT id, quantity FROM product_batches WHERE id = ? AND product_id = ? FOR UPDATE',
            ['id' => $batchId, 'product_id' => $productId]
        );
        if ($batch === null) {
            return;
        }
        $newQty = max(0, (int) $batch['quantity'] + $changeQty);
        $this->db->update('product_batches', ['quantity' => $newQty], 'id = ?', ['id' => $batchId]);
    }

}
