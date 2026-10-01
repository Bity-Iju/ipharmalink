<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Setting;

final class InventoryAlertService
{
    private const ALERT_TYPES = ['low_stock', 'out_of_stock', 'expiring_90', 'expiring_60', 'expiring_30', 'expired'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return array{scanned:int,triggered:int,resolved:int} */
    public function scan(): array
    {
        $products = $this->db->all(
            'SELECT id, pharmacy_id, name, stock_qty, min_stock_level, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) AS days_to_expiry
             FROM products WHERE deleted_at IS NULL ORDER BY id'
        );

        $result = ['scanned' => 0, 'triggered' => 0, 'resolved' => 0];
        foreach ($products as $product) {
            $changes = $this->syncProduct($product);
            $result['scanned']++;
            $result['triggered'] += $changes['triggered'];
            $result['resolved'] += $changes['resolved'];
        }

        $this->resolveDeletedProducts($result);
        return $result;
    }

    /** @return array{triggered:int,resolved:int} */
    public function syncProductById(int $productId): array
    {
        $product = $this->db->first(
            'SELECT id, pharmacy_id, name, stock_qty, min_stock_level, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) AS days_to_expiry
             FROM products WHERE id = ? AND deleted_at IS NULL',
            [$productId]
        );

        return $product === null ? ['triggered' => 0, 'resolved' => 0] : $this->syncProduct($product);
    }

    /** @param array<string,mixed> $product
     *  @return array{triggered:int,resolved:int}
     */
    private function syncProduct(array $product): array
    {
        $desired = $this->desiredAlerts($product);
        $counts = ['triggered' => 0, 'resolved' => 0];

        $this->db->transaction(function () use ($product, $desired, &$counts): void {
            foreach (self::ALERT_TYPES as $type) {
                $this->db->run(
                    'INSERT IGNORE INTO inventory_alerts (pharmacy_id, product_id, alert_type)
                     VALUES (?, ?, ?)',
                    [(int) $product['pharmacy_id'], (int) $product['id'], $type]
                );
                $state = $this->db->first(
                    'SELECT id, is_active FROM inventory_alerts
                     WHERE product_id = ? AND alert_type = ? FOR UPDATE',
                    [(int) $product['id'], $type]
                );
                if ($state === null) {
                    continue;
                }

                $shouldBeActive = isset($desired[$type]);
                if ($shouldBeActive && !(bool) $state['is_active']) {
                    $this->db->update('inventory_alerts', [
                        'is_active' => 1,
                        'first_triggered_at' => date('Y-m-d H:i:s'),
                        'last_notified_at' => date('Y-m-d H:i:s'),
                        'resolved_at' => null,
                    ], 'id = ?', [(int) $state['id']]);
                    $this->notify($product, $type, $desired[$type]);
                    $counts['triggered']++;
                } elseif (!$shouldBeActive && (bool) $state['is_active']) {
                    $this->db->update('inventory_alerts', [
                        'is_active' => 0,
                        'resolved_at' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [(int) $state['id']]);
                    $counts['resolved']++;
                }
            }
        });

        return $counts;
    }

    /** @param array<string,mixed> $product
     *  @return array<string,string>
     */
    private function desiredAlerts(array $product): array
    {
        if (!(bool) $this->db->value('SELECT is_active FROM products WHERE id = ?', [(int) $product['id']])) {
            return [];
        }

        $alerts = [];
        $stock = (int) $product['stock_qty'];
        $threshold = (int) $product['min_stock_level'];
        if ($threshold <= 0) {
            $threshold = Setting::getInt('system.low_stock_threshold_default', 10);
        }
        if ($stock === 0) {
            $alerts['out_of_stock'] = 'This product is out of stock.';
        } elseif ($threshold > 0 && $stock <= $threshold) {
            $alerts['low_stock'] = sprintf('Only %d unit(s) remain, below the minimum level of %d.', $stock, $threshold);
        }

        if ($product['expiry_date'] !== null) {
            $days = (int) $product['days_to_expiry'];
            if ($days < 0) {
                $alerts['expired'] = 'This product expired on ' . $product['expiry_date'] . '.';
            } elseif ($days <= 30) {
                $alerts['expiring_30'] = 'This product expires on ' . $product['expiry_date'] . ' (' . $days . ' days).';
            } elseif ($days <= 60) {
                $alerts['expiring_60'] = 'This product expires on ' . $product['expiry_date'] . ' (' . $days . ' days).';
            } elseif ($days <= 90) {
                $alerts['expiring_90'] = 'This product expires on ' . $product['expiry_date'] . ' (' . $days . ' days).';
            }
        }

        return $alerts;
    }

    /** @param array<string,mixed> $product */
    private function notify(array $product, string $type, string $message): void
    {
        $isExpiry = str_starts_with($type, 'expiring_') || $type === 'expired';
        (new NotificationService())
            ->toPharmacy(
                (int) $product['pharmacy_id'],
                $isExpiry ? 'stock.expiring' : 'stock.low',
                $isExpiry ? 'Expiry alert' : ($type === 'out_of_stock' ? 'Out of stock' : 'Low stock alert'),
                sprintf('%s %s', (string) $product['name'], $message),
                $isExpiry ? '/pharmacy/inventory/expiring' : '/pharmacy/inventory/low-stock',
                'product',
                (int) $product['id']
            )
            ->send(false);
    }

    /** @param array{scanned:int,triggered:int,resolved:int} $result */
    private function resolveDeletedProducts(array &$result): void
    {
        $rows = $this->db->all(
            'SELECT a.id FROM inventory_alerts a
             JOIN products p ON p.id = a.product_id
             WHERE a.is_active = 1 AND p.deleted_at IS NOT NULL'
        );
        foreach ($rows as $row) {
            $this->db->update('inventory_alerts', [
                'is_active' => 0,
                'resolved_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $row['id']]);
            $result['resolved']++;
        }
    }
}