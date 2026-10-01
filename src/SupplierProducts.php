<?php

declare(strict_types=1);

namespace IpharmaLink;

use PDO;
use RuntimeException;

final class SupplierProducts
{
    public static function create(PDO $db, int $supplierId, array $input): array
    {
        $db->beginTransaction();
        try {
            $catId = null;
            if (!empty($input['category'])) {
                $cat = $db->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
                $cat->execute([$input['category']]);
                $catId = $cat->fetchColumn();
            }

            $stmt = $db->prepare(
                'INSERT INTO products (supplier_id, category_id, name, slug, generic_name, brand, manufacturer, '
                . 'active_ingredient, strength, dosage_form, sku, barcode, description, prescription_required, '
                . 'classification, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $input['name']), '-')) . '-' . uniqid();
            $stmt->execute([
                $supplierId,
                $catId,
                trim((string) ($input['name'] ?? '')),
                $slug,
                trim((string) ($input['generic_name'] ?? '')) ?: null,
                trim((string) ($input['brand'] ?? '')) ?: null,
                trim((string) ($input['manufacturer'] ?? '')) ?: null,
                trim((string) ($input['active_ingredient'] ?? '')) ?: null,
                trim((string) ($input['strength'] ?? '')) ?: null,
                trim((string) ($input['dosage_form'] ?? '')) ?: null,
                trim((string) ($input['sku'] ?? '')),
                trim((string) ($input['barcode'] ?? '')) ?: null,
                trim((string) ($input['description'] ?? '')) ?: null,
                (bool) ($input['prescription_required'] ?? false),
                $input['classification'] ?? 'medicine',
                'active',
            ]);
            $productId = (int) $db->lastInsertId();

            if (!empty($input['packaging']) && is_array($input['packaging'])) {
                $pkg = $db->prepare('INSERT INTO product_packaging (product_id, unit_name, units_per_parent, is_order_unit, sort_order) VALUES (?, ?, ?, ?, ?)');
                $sort = 0;
                foreach ($input['packaging'] as $p) {
                    $pkg->execute([$productId, $p['unit'] ?? 'unit', (int) ($p['units_per_parent'] ?? 1), (bool) ($p['is_order_unit'] ?? false), $sort++]);
                }
            } else {
                $db->prepare('INSERT INTO product_packaging (product_id, unit_name, is_order_unit) VALUES (?, ?, 1)')->execute([$productId, 'carton']);
            }

            if (!empty($input['batches']) && is_array($input['batches'])) {
                $batch = $db->prepare('INSERT INTO product_batches (product_id, batch_number, manufacturing_date, expiry_date, quantity, cost_price) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($input['batches'] as $b) {
                    $batch->execute([
                        $productId,
                        trim((string) ($b['batch_number'] ?? '')),
                        $b['manufacturing_date'] ?? null,
                        $b['expiry_date'],
                        (int) ($b['quantity'] ?? 0),
                        (float) ($b['cost_price'] ?? 0),
                    ]);
                }
            }

            if (!empty($input['pricing']) && is_array($input['pricing'])) {
                $pkgId = $db->prepare('SELECT id FROM product_packaging WHERE product_id = ? ORDER BY sort_order LIMIT 1')->execute([$productId])->fetchColumn();
                $price = $db->prepare('INSERT INTO wholesale_price_tiers (product_id, packaging_id, min_quantity, max_quantity, price, price_type) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($input['pricing'] as $p) {
                    $price->execute([
                        $productId,
                        $pkgId,
                        (int) ($p['min_quantity'] ?? 1),
                        isset($p['max_quantity']) ? (int) $p['max_quantity'] : null,
                        (float) ($p['price'] ?? 0),
                        $p['type'] ?? 'standard',
                    ]);
                }
            }

            $db->commit();
            return ['id' => $productId, 'name' => $input['name'], 'sku' => $input['sku']];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function list(PDO $db, int $supplierId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $db->prepare(
            'SELECT p.id, p.name, p.sku, p.generic_name, p.brand, p.status, '
            . 'SUM(CASE WHEN pb.expiry_date > CURDATE() THEN pb.quantity ELSE 0 END) AS available_stock, '
            . 'COUNT(DISTINCT pb.id) AS batch_count FROM products p '
            . 'LEFT JOIN product_batches pb ON pb.product_id = p.id '
            . 'WHERE p.supplier_id = ? GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$supplierId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function updatePricing(PDO $db, int $productId, int $supplierId, array $tiers): void
    {
        $verify = $db->prepare('SELECT supplier_id FROM products WHERE id = ? LIMIT 1');
        $verify->execute([$productId]);
        if ((int) $verify->fetchColumn() !== $supplierId) {
            throw new RuntimeException('Unauthorized.');
        }

        $db->prepare('DELETE FROM wholesale_price_tiers WHERE product_id = ?')->execute([$productId]);
        $pkgId = $db->prepare('SELECT id FROM product_packaging WHERE product_id = ? LIMIT 1')->execute([$productId])->fetchColumn();
        $insert = $db->prepare('INSERT INTO wholesale_price_tiers (product_id, packaging_id, min_quantity, max_quantity, price, price_type) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($tiers as $tier) {
            $insert->execute([
                $productId,
                $pkgId,
                (int) ($tier['min_quantity'] ?? 1),
                isset($tier['max_quantity']) ? (int) $tier['max_quantity'] : null,
                (float) ($tier['price'] ?? 0),
                $tier['type'] ?? 'standard',
            ]);
        }
    }
}
