<?php

declare(strict_types=1);

namespace IpharmaLink;

use PDO;
use RuntimeException;

final class WholesaleOrders
{
    public static function checkout(PDO $db, int $buyerId, array $input): array
    {
        $buyerOrg = $db->prepare('SELECT id FROM organizations WHERE owner_user_id = ? AND type = "retail_pharmacy" LIMIT 1');
        $buyerOrg->execute([$buyerId]);
        $buyerOrgId = $buyerOrg->fetchColumn();
        if (!$buyerOrgId) throw new RuntimeException('Retail pharmacy profile not found.');

        $db->beginTransaction();
        try {
            $cartId = (int) ($input['cart_id'] ?? 0);
            $cartItems = $db->prepare(
                'SELECT ci.product_id, p.supplier_id, ci.packaging_id, ci.quantity, ci.unit_price '
                . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
                . 'WHERE ci.cart_id = ? ORDER BY p.supplier_id'
            );
            $cartItems->execute([$cartId]);
            $items = $cartItems->fetchAll();
            if (empty($items)) throw new RuntimeException('Cart is empty.');

            $suppliers = [];
            foreach ($items as $item) {
                $sid = (int) $item['supplier_id'];
                if (!isset($suppliers[$sid])) $suppliers[$sid] = [];
                $suppliers[$sid][] = $item;
            }

            $parentOrderNum = 'PO-' . date('Ymd') . '-' . random_int(1000, 9999);
            $orders = [];
            $lineTotal = 0;
            foreach ($suppliers as $supplierId => $supplierItems) {
                $subOrderNum = $parentOrderNum . '-' . chr(65 + count($orders));
                $subtotal = 0;
                foreach ($supplierItems as $si) {
                    $subtotal += (float) $si['quantity'] * (float) $si['unit_price'];
                }

                $orderStmt = $db->prepare(
                    'INSERT INTO wholesale_orders (order_number, buyer_id, supplier_id, status, subtotal, total, delivery_address, notes) '
                    . 'VALUES (?, ?, ?, "submitted", ?, ?, ?, ?)'
                );
                $address = json_encode(['address' => $input['delivery_address'] ?? '', 'phone' => $input['phone'] ?? '']);
                $orderStmt->execute([
                    $subOrderNum,
                    $buyerOrgId,
                    $supplierId,
                    $subtotal,
                    $subtotal,
                    $address,
                    trim((string) ($input['notes'] ?? '')) ?: null,
                ]);
                $orderId = (int) $db->lastInsertId();

                foreach ($supplierItems as $si) {
                    $itemStmt = $db->prepare(
                        'INSERT INTO wholesale_order_items (order_id, product_id, packaging_id, quantity, unit_price, line_total) '
                        . 'VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $lt = (float) $si['quantity'] * (float) $si['unit_price'];
                    $itemStmt->execute([
                        $orderId,
                        (int) $si['product_id'],
                        (int) $si['packaging_id'],
                        (int) $si['quantity'],
                        (float) $si['unit_price'],
                        $lt,
                    ]);
                }

                $orders[] = ['order_id' => $orderId, 'order_number' => $subOrderNum, 'supplier_id' => $supplierId, 'subtotal' => $subtotal];
                $lineTotal += $subtotal;
            }

            $db->prepare('UPDATE carts SET status = "converted" WHERE id = ?')->execute([$cartId]);
            $db->commit();
            return ['parent_order' => $parentOrderNum, 'orders' => $orders, 'total' => $lineTotal];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getOrder(PDO $db, int $orderId, int $orgId): array
    {
        $order = $db->prepare(
            'SELECT o.id, o.order_number, o.status, o.subtotal, o.total, o.delivery_address, o.created_at, '
            . 'b.business_name AS buyer, s.business_name AS supplier FROM wholesale_orders o '
            . 'JOIN organizations b ON b.id = o.buyer_id JOIN organizations s ON s.id = o.supplier_id '
            . 'WHERE o.id = ? AND (o.buyer_id = ? OR o.supplier_id = ?) LIMIT 1'
        );
        $order->execute([$orderId, $orgId, $orgId]);
        $o = $order->fetch();
        if (!$o) throw new RuntimeException('Order not found.');

        $items = $db->prepare(
            'SELECT oi.product_id, p.name, p.sku, oi.packaging_id, pp.unit_name, oi.quantity, oi.unit_price, oi.line_total '
            . 'FROM wholesale_order_items oi JOIN products p ON p.id = oi.product_id '
            . 'JOIN product_packaging pp ON pp.id = oi.packaging_id WHERE oi.order_id = ?'
        );
        $items->execute([$orderId]);
        $o['items'] = $items->fetchAll();
        return $o;
    }

    public static function updateStatus(PDO $db, int $orderId, int $supplierId, string $status): void
    {
        $verify = $db->prepare('SELECT supplier_id FROM wholesale_orders WHERE id = ?');
        $verify->execute([$orderId]);
        if ((int) $verify->fetchColumn() !== $supplierId) throw new RuntimeException('Unauthorized.');

        $allowed = ['accepted', 'rejected', 'processing', 'ready', 'dispatched', 'in_transit', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid status.');

        $db->prepare('UPDATE wholesale_orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    }
}
