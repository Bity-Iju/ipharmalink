<?php

/**
 * iPharmaLink :: Cart service
 * ---------------------------------------------------------------------------
 * Cart rows live in MySQL (not the session) so a guest cart survives a
 * device change and can be merged on login. Prices are NEVER read from the
 * cart — they are resolved from the product table at every calculation, so a
 * tampered client cannot influence a total.
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\Setting;
use App\Session;
use App\ValidationException;

final class CartService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    // -----------------------------------------------------------------------
    //  Identity & resolution
    // -----------------------------------------------------------------------

    public function cartId(): int
    {
        $userId = Auth::id();
        if ($userId !== null) {
            $cart = $this->db->first(
                'SELECT id FROM carts WHERE user_id = ? AND status = \'active\' LIMIT 1',
                ['user_id' => $userId]
            );
            if ($cart !== null) {
                return (int) $cart['id'];
            }
            return $this->db->insert('carts', ['user_id' => $userId, 'status' => 'active']);
        }

        $key   = Session::cartKey();
        $cart  = $this->db->first(
            'SELECT id FROM carts WHERE session_key = ? AND status = \'active\' LIMIT 1',
            ['session_key' => $key]
        );
        if ($cart !== null) {
            return (int) $cart['id'];
        }
        return $this->db->insert('carts', ['session_key' => $key, 'status' => 'active']);
    }

    /** Merge the guest cart into the user's cart after sign-in. */
    public function mergeGuestCart(): void
    {
        $userId   = Auth::id();
        $guestKey = Session::get('_cart_key');
        if ($userId === null || $guestKey === null) {
            return;
        }

        $guestCart = $this->db->first(
            'SELECT id FROM carts WHERE session_key = ? AND status = \'active\' LIMIT 1',
            ['session_key' => $guestKey]
        );
        if ($guestCart === null) {
            return;
        }

        $guestId = (int) $guestCart['id'];
        $userCart = $this->db->first(
            'SELECT id FROM carts WHERE user_id = ? AND status = \'active\' LIMIT 1',
            ['user_id' => $userId]
        );
        $userCartId = $userCart === null
            ? $this->db->insert('carts', ['user_id' => $userId, 'status' => 'active'])
            : (int) $userCart['id'];

        $this->db->transaction(function () use ($guestId, $userCartId): void {
            $guestItems = $this->db->all('SELECT product_id, quantity FROM cart_items WHERE cart_id = ?', ['cart_id' => $guestId]);
            foreach ($guestItems as $item) {
                $existing = $this->db->value(
                    'SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1',
                    ['cart_id' => $userCartId, 'product_id' => $item['product_id']]
                );
                if ($existing) {
                    $this->db->run(
                        'UPDATE cart_items SET quantity = quantity + ? WHERE id = ?',
                        ['qty' => (int) $item['quantity'], 'id' => $existing]
                    );
                } else {
                    $this->db->run(
                        'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)',
                        ['cart_id' => $userCartId, 'product_id' => $item['product_id'], 'quantity' => $item['quantity']]
                    );
                }
            }
            $this->db->delete('carts', 'id = ?', ['id' => $guestId]);
        });

        Session::forget('_cart_key');
    }

    // -----------------------------------------------------------------------
    //  Mutations
    // -----------------------------------------------------------------------

    /**
     * @throws ValidationException when the product is unavailable
     */
    public function add(int $productId, int $quantity = 1): void
    {
        $quantity = max(1, min($quantity, 99));
        $product  = $this->sellableProduct($productId);

        if ($product['stock_qty'] < 1) {
            throw new ValidationException(['product_id' => ['This product is currently out of stock.']]);
        }

        $cartId   = $this->cartId();
        $existing = $this->db->value(
            'SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1',
            ['cart_id' => $cartId, 'product_id' => $productId]
        );

        $newQuantity = min($quantity + (int) ($existing ? $this->db->value(
            'SELECT quantity FROM cart_items WHERE id = ?',
            ['id' => $existing]
        ) : 0), 99);

        // Never let a customer hold more than the pharmacy has.
        if ($newQuantity > (int) $product['stock_qty']) {
            throw new ValidationException(['product_id' => [
                "Only {$product['stock_qty']} units are available at {$product['pharmacy_name']}.",
            ]]);
        }

        if ($existing) {
            $this->db->update('cart_items', ['quantity' => $newQuantity], 'id = ?', ['id' => $existing]);
        } else {
            $this->db->insert('cart_items', [
                'cart_id'    => $cartId,
                'product_id' => $productId,
                'quantity'   => $newQuantity,
            ]);
        }
    }

    public function updateQuantity(int $productId, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($productId);
            return;
        }
        $quantity = min($quantity, 99);
        $product  = $this->sellableProduct($productId);

        if ($quantity > (int) $product['stock_qty']) {
            throw new ValidationException(['quantity' => [
                "Only {$product['stock_qty']} units are in stock at {$product['pharmacy_name']}.",
            ]]);
        }

        $this->db->run(
            'UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?',
            ['qty' => $quantity, 'cart_id' => $this->cartId(), 'product_id' => $productId]
        );
    }

    public function remove(int $productId): void
    {
        $this->db->run(
            'DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?',
            ['cart_id' => $this->cartId(), 'product_id' => $productId]
        );
    }

    public function clear(): void
    {
        $this->db->delete('cart_items', 'cart_id = ?', ['cart_id' => $this->cartId()]);
    }

    // -----------------------------------------------------------------------
    //  Reads
    // -----------------------------------------------------------------------

    public function rawCount(): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?',
            ['cart_id' => $this->cartId()]
        );
    }

    public function isEmpty(): bool
    {
        return $this->rawCount() === 0;
    }

    /**
     * Cart contents with server-resolved prices and availability, grouped by
     * pharmacy — the same grouping the checkout will split on.
     *
     * @return array{groups:list<array<string,mixed>>, itemCount:int, subtotal:float, problems:list<string>}
     */
    public function contents(): array
    {
        $rows = $this->db->all(
            'SELECT ci.id AS cart_item_id, ci.quantity,
                    p.id, p.slug, p.name, p.sku, p.generic_name, p.requires_prescription, p.product_class,
                    p.price, p.discount_price, p.tax_rate, p.stock_qty, p.is_active, p.is_visible,
                    p.expiry_date,
                    ph.id AS pharmacy_id, ph.name AS pharmacy_name, ph.slug AS pharmacy_slug, ph.logo AS pharmacy_logo,
                    ph.city, ph.state, ph.delivery_available, ph.pickup_available, ph.delivery_fee,
                    ph.free_delivery_threshold, ph.min_order_value, ph.status AS pharmacy_status,
                    (SELECT pi.file_path FROM product_images pi
                      WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM cart_items ci
             JOIN products  p  ON p.id = ci.product_id
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE ci.cart_id = ? AND p.deleted_at IS NULL
             ORDER BY ph.name ASC, p.name ASC',
            ['cart_id' => $this->cartId()]
        );

        $groups    = [];
        $problems  = [];
        $itemCount = 0;
        $subtotal  = 0.0;

        foreach ($rows as $row) {
            $pharmacyId = (int) $row['pharmacy_id'];

            if (!isset($groups[$pharmacyId])) {
                $groups[$pharmacyId] = [
                    'pharmacy_id'      => $pharmacyId,
                    'pharmacy_name'    => $row['pharmacy_name'],
                    'pharmacy_slug'    => $row['pharmacy_slug'],
                    'pharmacy_logo'    => $row['pharmacy_logo'],
                    'city'             => $row['city'],
                    'state'            => $row['state'],
                    'delivery_available' => (bool) $row['delivery_available'],
                    'pickup_available'   => (bool) $row['pickup_available'],
                    'delivery_fee'     => (float) $row['delivery_fee'],
                    'free_delivery_threshold' => (float) $row['free_delivery_threshold'],
                    'min_order_value'  => (float) $row['min_order_value'],
                    'pharmacy_status'  => $row['pharmacy_status'],
                    'items'            => [],
                    'subtotal'         => 0.0,
                    'has_prescription' => false,
                ];
            }

            $quantity   = (int) $row['quantity'];
            $unitPrice  = $this->effectivePrice((float) $row['price'], $row['discount_price']);
            $lineTotal  = $unitPrice * $quantity;
            $available  = (int) $row['stock_qty'];

            // ---- availability checks (surfaced, not silently dropped) -----
            $unavailable = false;
            if ($row['pharmacy_status'] !== 'approved') {
                $problems[] = "{$row['pharmacy_name']} is not currently trading.";
                $unavailable = true;
            } elseif ((int) $row['is_active'] !== 1 || (int) $row['is_visible'] !== 1) {
                $problems[] = "\"{$row['name']}\" is no longer available.";
                $unavailable = true;
            } elseif ($available === 0) {
                $problems[] = "\"{$row['name']}\" is out of stock.";
                $unavailable = true;
            } elseif ($quantity > $available) {
                $problems[] = "Only {$available} units of \"{$row['name']}\" remain — quantity reduced.";
                $row['quantity'] = $available;
                $quantity        = $available;
                $lineTotal       = $unitPrice * $quantity;
                $this->updateQuantity((int) $row['id'], $quantity);
            }
            if ($row['expiry_date'] !== null && strtotime((string) $row['expiry_date']) < strtotime('today')) {
                $problems[] = "\"{$row['name']}\" has passed its expiry date and was removed.";
                $this->remove((int) $row['id']);
                continue;
            }

            $itemCount += $quantity;
            $subtotal  += $lineTotal;
            $groups[$pharmacyId]['subtotal'] += $lineTotal;

            if ((int) $row['requires_prescription'] === 1) {
                $groups[$pharmacyId]['has_prescription'] = true;
            }

            if (!$unavailable) {
                $groups[$pharmacyId]['items'][] = [
                    'product_id'   => (int) $row['id'],
                    'cart_item_id' => (int) $row['cart_item_id'],
                    'slug'         => $row['slug'],
                    'name'         => $row['name'],
                    'generic_name' => $row['generic_name'],
                    'sku'          => $row['sku'],
                    'image'        => $row['image'],
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'line_total'   => $lineTotal,
                    'stock_qty'    => $available,
                    'tax_rate'     => (float) $row['tax_rate'],
                    'requires_prescription' => (int) $row['requires_prescription'],
                    'product_class' => $row['product_class'],
                ];
            }
        }

        // Drop pharmacies whose every line was removed.
        $groups = array_values(array_filter(
            $groups,
            static fn(array $group): bool => $group['items'] !== []
        ));

        return [
            'groups'    => $groups,
            'itemCount' => $itemCount,
            'subtotal'  => $subtotal,
            'problems'  => array_values(array_unique($problems)),
        ];
    }

    /** Number of distinct pharmacies in the cart (drives the UI notice). */
    public function pharmacyCount(): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(DISTINCT p.pharmacy_id)
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.cart_id = ?',
            ['cart_id' => $this->cartId()]
        );
    }

    public function needsPrescription(): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*)
             FROM cart_items ci JOIN products p ON p.id = ci.product_id
             WHERE ci.cart_id = ? AND p.requires_prescription = 1',
            ['cart_id' => $this->cartId()]
        ) > 0;
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /** Effective selling price, always resolved server-side. */
    public function effectivePrice(float $price, mixed $discountPrice): float
    {
        $discount = is_numeric($discountPrice) ? (float) $discountPrice : null;
        // A "discount" above list price is treated as no discount.
        if ($discount === null || $discount <= 0 || $discount >= $price) {
            return round($price, 2);
        }
        return round($discount, 2);
    }

    /** @return array<string,mixed> */
    private function sellableProduct(int $productId): array
    {
        $product = $this->db->first(
            'SELECT p.*, ph.name AS pharmacy_name, ph.status AS pharmacy_status
             FROM products p JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1',
            ['id' => $productId]
        );
        if ($product === null) {
            throw new ValidationException(['product_id' => ['That product is no longer available.']]);
        }
        if ($product['pharmacy_status'] !== 'approved') {
            throw new ValidationException(['product_id' => ['This pharmacy is not currently trading.']]);
        }
        if ((int) $product['is_active'] !== 1 || (int) $product['is_visible'] !== 1) {
            throw new ValidationException(['product_id' => ['That product is not available for purchase.']]);
        }
        return $product;
    }
}
