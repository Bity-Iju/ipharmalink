<?php

declare(strict_types=1);

namespace IpharmaLink;

use PDO;
use RuntimeException;

final class WholesaleCart
{
    public static function getOrCreate(PDO $db, int $buyerId): array
    {
        $stmt = $db->prepare('SELECT id FROM carts WHERE buyer_id = ? AND type = "wholesale" AND status = "active" LIMIT 1');
        $stmt->execute([$buyerId]);
        $cartId = $stmt->fetchColumn();
        if (!$cartId) {
            $insert = $db->prepare('INSERT INTO carts (buyer_id, type, status) VALUES (?, "wholesale", "active")');
            $insert->execute([$buyerId]);
            $cartId = (int) $db->lastInsertId();
        }
        return ['id' => $cartId];
    }

    public static function addItem(PDO $db, int $cartId, int $buyerId, int $productId, int $packagingId, int $quantity): array
    {
        $verify = $db->prepare('SELECT buyer_id FROM carts WHERE id = ? AND type = "wholesale"');
        $verify->execute([$cartId]);
        if ((int) $verify->fetchColumn() !== $buyerId) {
            throw new RuntimeException('Cart not found.');
        }

        $product = $db->prepare('SELECT p.id, p.supplier_id, o.business_name FROM products p JOIN organizations o ON o.id = p.supplier_id WHERE p.id = ? AND p.status = "active"');
        $product->execute([$productId]);
        $prod = $product->fetch();
        if (!$prod) throw new RuntimeException('Product not found or inactive.');

        $pkg = $db->prepare('SELECT id, units_per_parent FROM product_packaging WHERE id = ? AND product_id = ?');
        $pkg->execute([$packagingId, $productId]);
        $packaging = $pkg->fetch();
        if (!$packaging) throw new RuntimeException('Invalid packaging.');

        $price = $db->prepare('SELECT price FROM wholesale_price_tiers WHERE product_id = ? AND packaging_id = ? AND min_quantity <= ? AND (max_quantity IS NULL OR max_quantity >= ?) ORDER BY min_quantity DESC LIMIT 1');
        $price->execute([$productId, $packagingId, $quantity, $quantity]);
        $unitPrice = (float) ($price->fetchColumn() ?: 0);
        if ($unitPrice <= 0) throw new RuntimeException('No pricing found for this quantity.');

        $existing = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND packaging_id = ?');
        $existing->execute([$cartId, $productId, $packagingId]);
        $item = $existing->fetch();
        if ($item) {
            $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')->execute([$quantity, $item['id']]);
            $itemId = $item['id'];
        } else {
            $insert = $db->prepare('INSERT INTO cart_items (cart_id, product_id, packaging_id, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$cartId, $productId, $packagingId, $quantity, $unitPrice]);
            $itemId = (int) $db->lastInsertId();
        }

        return ['item_id' => $itemId, 'supplier' => $prod['business_name'], 'unit_price' => $unitPrice, 'total' => $unitPrice * $quantity];
    }

    public static function getCart(PDO $db, int $cartId, int $buyerId): array
    {
        $verify = $db->prepare('SELECT id FROM carts WHERE id = ? AND buyer_id = ? AND type = "wholesale"');
        $verify->execute([$cartId, $buyerId]);
        if (!$verify->fetchColumn()) throw new RuntimeException('Cart not found.');

        $items = $db->prepare(
            'SELECT ci.id, p.name, p.sku, o.business_name AS supplier, ci.quantity, ci.unit_price, (ci.quantity * ci.unit_price) AS line_total, '
            . 'pp.unit_name, o.id AS supplier_id FROM cart_items ci JOIN products p ON p.id = ci.product_id '
            . 'JOIN product_packaging pp ON pp.id = ci.packaging_id JOIN organizations o ON o.id = p.supplier_id '
            . 'WHERE ci.cart_id = ? ORDER BY o.business_name, p.name'
        );
        $items->execute([$cartId]);
        $cartItems = $items->fetchAll();

        $grouped = [];
        $total = 0;
        foreach ($cartItems as $item) {
            $sid = $item['supplier_id'];
            if (!isset($grouped[$sid])) $grouped[$sid] = ['supplier' => $item['supplier'], 'items' => [], 'subtotal' => 0];
            $grouped[$sid]['items'][] = $item;
            $grouped[$sid]['subtotal'] += (float) $item['line_total'];
            $total += (float) $item['line_total'];
        }

        return ['cart_id' => $cartId, 'items_count' => count($cartItems), 'suppliers' => array_values($grouped), 'total' => $total];
    }

    public static function removeItem(PDO $db, int $cartId, int $buyerId, int $itemId): void
    {
        $verify = $db->prepare('SELECT ci.id FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE ci.id = ? AND c.id = ? AND c.buyer_id = ?');
        $verify->execute([$itemId, $cartId, $buyerId]);
        if (!$verify->fetchColumn()) throw new RuntimeException('Item not found.');
        $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$itemId]);
    }
}
