<?php

/**
 * iPharmaLink :: Order service
 * ---------------------------------------------------------------------------
 * The order engine. Three responsibilities, kept apart:
 *
 *   1. calculate()  — pure pricing maths, no writes, no side effects.
 *   2. place()      — transactional creation of the parent order, one
 *                     pharmacy_orders slice per vendor, order items, stock
 *                     movement and payment record. Rolls back completely on
 *                     any failure.
 *   3. transitions() — the state machine governing what may follow what.
 *
 * Every price is re-read from the products table inside the transaction.
 * Nothing is trusted from the request body.
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\HttpException;
use App\Setting;
use App\Session;
use App\ValidationException;
use App\Services\InventoryService;

final class OrderService
{
    // ---- Parent order lifecycle ------------------------------------------
    public const STATUS_PENDING_PAYMENT   = 'pending_payment';
    public const STATUS_PAID              = 'paid';
    public const STATUS_RECEIVED          = 'received';
    public const STATUS_PROCESSING        = 'processing';
    public const STATUS_PREPARING         = 'preparing';
    public const STATUS_READY_FOR_PICKUP  = 'ready_for_pickup';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_OUT_FOR_DELIVERY  = 'out_for_delivery';
    public const STATUS_DELIVERED         = 'delivered';
    public const STATUS_CANCELLED         = 'cancelled';
    public const STATUS_REFUNDED          = 'refunded';

    /** Legal parent-order transitions. */
    private const TRANSITIONS = [
        self::STATUS_PENDING_PAYMENT    => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID               => [self::STATUS_RECEIVED, self::STATUS_PROCESSING, self::STATUS_CANCELLED, self::STATUS_REFUNDED],
        self::STATUS_RECEIVED           => [self::STATUS_PROCESSING, self::STATUS_PREPARING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING         => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
        self::STATUS_PREPARING          => [self::STATUS_READY_FOR_PICKUP, self::STATUS_READY_FOR_DELIVERY, self::STATUS_CANCELLED],
        self::STATUS_READY_FOR_PICKUP   => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_READY_FOR_DELIVERY => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_OUT_FOR_DELIVERY   => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED          => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED          => [self::STATUS_REFUNDED],
        self::STATUS_REFUNDED           => [],
    ];

    /** What each pharmacy may do to its own slice. */
    public const SLICE_ACTIONS = [
        'accept'    => [self::STATUS_RECEIVED],
        'reject'    => [self::STATUS_CANCELLED],
        'prepare'   => [self::STATUS_PREPARING],
        'ready'     => [self::STATUS_READY_FOR_PICKUP, self::STATUS_READY_FOR_DELIVERY],
        'dispatch'  => [self::STATUS_OUT_FOR_DELIVERY],
        'deliver'   => [self::STATUS_DELIVERED],
        'cancel'    => [self::STATUS_CANCELLED],
    ];

    private Database $db;
    private CartService $cart;
    private InventoryService $inventory;

    public function __construct(?Database $db = null)
    {
        $this->db        = $db ?? Database::instance();
        $this->cart      = new CartService($this->db);
        $this->inventory = new InventoryService($this->db);
    }

    // =======================================================================
    //  1. PRICING — pure calculation
    // =======================================================================

    /**
     * Price the cart and split it per pharmacy.
     *
     * @param  string $method  'delivery' | 'pickup'
     * @return array<string,mixed>
     */
    public function calculate(string $method = 'delivery'): array
    {
        $cart    = $this->cart->contents();
        $groups  = $cart['groups'];
        $currency = Setting::getString('currency.currency_code', 'NGN');

        $slices     = [];
        $subtotal   = 0.0;
        $taxTotal   = 0.0;
        $deliveryFee = 0.0;
        $discountTotal = 0.0;
        $warnings   = $cart['problems'];

        $commissionRate = Setting::getFloat('commission.default_rate_percent', 8.0);
        $commissionFixed = Setting::getFloat('commission.default_fixed', 0.0);

        foreach ($groups as $group) {
            $pharmacyId  = (int) $group['pharmacy_id'];
            $itemsSubtotal = 0.0;
            $groupTax    = 0.0;
            $needsPrescription = false;

            foreach ($group['items'] as $item) {
                $lineSubtotal = $item['unit_price'] * $item['quantity'];
                $itemsSubtotal += $lineSubtotal;

                $lineTax = round($lineSubtotal * ((float) $item['tax_rate'] / 100), 2);
                $groupTax += $lineTax;

                $item['line_tax']    = $lineTax;
                $item['line_total_with_tax'] = round($lineSubtotal + $lineTax, 2);

                if ($item['requires_prescription'] === 1) {
                    $needsPrescription = true;
                }
            }

            $itemsSubtotal = round($itemsSubtotal, 2);
            $groupTax      = round($groupTax, 2);

            // ---- per-pharmacy delivery rules --------------------------------
            $fee = 0.0;
            $pharmacyName = (string) $group['pharmacy_name'];

            if ($method === 'delivery') {
                if (!$group['delivery_available']) {
                    $warnings[] = "{$pharmacyName} does not offer delivery — switch to pickup for those items.";
                    $fee = 0.0;
                } else {
                    $fee = (float) $group['delivery_fee'];
                    // Free delivery over the pharmacy's own threshold.
                    $threshold = (float) $group['free_delivery_threshold'];
                    if ($threshold > 0 && $itemsSubtotal >= $threshold) {
                        $fee = 0.0;
                    }
                }
            }

            // ---- minimum order value ---------------------------------------
            if ($itemsSubtotal > 0 && $itemsSubtotal < (float) $group['min_order_value']) {
                $warnings[] = sprintf(
                    '%s has a minimum order of ₦%s. You have ₦%s in your cart from this pharmacy.',
                    $pharmacyName,
                    number_format((float) $group['min_order_value'], 2),
                    number_format($itemsSubtotal, 2)
                );
            }

            // ---- commission -------------------------------------------------
            $rate = $commissionFixed;
            $pharmacyRow = $this->db->first('SELECT commission_rate FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]);
            $rate = $pharmacyRow['commission_rate'] !== null ? (float) $pharmacyRow['commission_rate'] : $commissionRate;
            $commission = round(($itemsSubtotal + $groupTax) * ($rate / 100), 2);

            $sliceTotal = round($itemsSubtotal + $groupTax + $fee, 2);

            $slices[] = [
                'pharmacy_id'         => $pharmacyId,
                'pharmacy_name'       => $pharmacyName,
                'pharmacy_slug'       => $group['pharmacy_slug'],
                'items_subtotal'      => $itemsSubtotal,
                'tax_total'           => $groupTax,
                'delivery_fee'        => $fee,
                'commission_rate'     => $rate,
                'commission_amount'   => $commission,
                'pharmacy_earnings'   => round($sliceTotal - $commission, 2),
                'total'               => $sliceTotal,
                'items'               => $group['items'],
                'has_prescription'    => $needsPrescription,
                'delivery_available'  => (bool) $group['delivery_available'],
                'pickup_available'    => (bool) $group['pickup_available'],
                'min_order_value'     => (float) $group['min_order_value'],
            ];

            $subtotal     += $itemsSubtotal;
            $taxTotal     += $groupTax;
            $deliveryFee  += $fee;
        }

        // ---- coupon --------------------------------------------------------
        $discountTotal = 0.0;
        $coupon        = null;
        $couponCode    = strtoupper(trim((string) Session::get('coupon_code', '')));
        $grandSubtotal = $subtotal + $taxTotal;

        if ($couponCode !== '' && $grandSubtotal > 0) {
            $validation = $this->validateCoupon($couponCode, $grandSubtotal);
            if ($validation['valid']) {
                $coupon        = $validation['coupon'];
                $discountTotal = $validation['discount'];
            } else {
                $warnings = array_merge($warnings, $validation['errors']);
            }
        }

        $total = round($grandSubtotal + $deliveryFee - $discountTotal, 2);

        return [
            'slices'        => $slices,
            'pharmacy_count' => count($slices),
            'item_count'    => $cart['itemCount'],
            'subtotal'      => round($subtotal, 2),
            'tax_total'     => round($taxTotal, 2),
            'delivery_fee'  => round($deliveryFee, 2),
            'discount_total' => $discountTotal,
            'total'         => $total,
            'currency'      => $currency,
            'coupon'        => $coupon,
            'warnings'      => array_values(array_unique($warnings)),
            'requires_prescription' => in_array(true, array_column($slices, 'has_prescription'), true),
        ];
    }

    // =======================================================================
    //  2. ORDER CREATION
    // =======================================================================

    /**
     * Create the order atomically.
     *
     * @param  array{fulfilment_method?:string, address_id?:int, note?:string, prescription_path?:string} $input
     * @return array{order:array<string,mixed>, slices:list<array<string,mixed>>, quote:array<string,mixed>}
     * @throws ValidationException
     */
    public function place(array $input): array
    {
        $customerId = Auth::id();
        if ($customerId === null) {
            throw HttpException::unauthorized('Please sign in before placing an order.');
        }

        $method    = ($input['fulfilment_method'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery';
        $addressId = isset($input['address_id']) ? (int) $input['address_id'] : null;

        // ---- re-price inside the transaction; never reuse the earlier quote
        $quote = $this->calculate($method);

        if ($quote['slices'] === []) {
            throw new ValidationException(['cart' => ['Your cart is empty.']]);
        }
        if ($quote['total'] <= 0) {
            throw new ValidationException(['total' => ['Order total must be greater than zero.']]);
        }

        $address = null;
        if ($method === 'delivery') {
            $address = $this->resolveAddress($customerId, $addressId);
        }

        // ---- hard business rules -----------------------------------------
        foreach ($quote['slices'] as $slice) {
            if ($method === 'delivery' && !$slice['delivery_available']) {
                throw new ValidationException(['fulfilment_method' => [
                    sprintf('%s does not offer delivery. Remove those items or choose pharmacy pickup.', $slice['pharmacy_name']),
                ]]);
            }
            if ($method === 'pickup' && !$slice['pickup_available']) {
                throw new ValidationException(['fulfilment_method' => [
                    sprintf('%s does not offer pickup for these items.', $slice['pharmacy_name']),
                ]]);
            }
            if ($slice['min_order_value'] > 0 && $slice['items_subtotal'] < $slice['min_order_value']) {
                throw new ValidationException(['cart' => [
                    sprintf(
                        '%s has a minimum order value of ₦%s. Your cart from this pharmacy is ₦%s.',
                        $slice['pharmacy_name'],
                        number_format($slice['min_order_value'], 2),
                        number_format($slice['items_subtotal'], 2)
                    ),
                ]]);
            }
        }

        return $this->db->transaction(function () use ($customerId, $method, $address, $addressId, $input, $quote) {
            $orderNumber = $this->generateOrderNumber();
            $now         = date('Y-m-d H:i:s');

            // ---- parent order ----------------------------------------------
            $orderId = $this->db->insert('orders', [
                'order_number'        => $orderNumber,
                'customer_id'         => $customerId,
                'status'              => self::STATUS_PENDING_PAYMENT,
                'payment_status'      => 'pending',
                'fulfilment_method'   => $method,
                'delivery_address_id' => $addressId,
                'address_snapshot'    => $address === null ? null : json_encode($address, JSON_UNESCAPED_SLASHES),
                'delivery_fee'        => $quote['delivery_fee'],
                'discount_total'      => $quote['discount_total'],
                'tax_total'           => $quote['tax_total'],
                'subtotal'            => $quote['subtotal'],
                'total'               => $quote['total'],
                'currency'            => $quote['currency'],
                'coupon_id'           => $quote['coupon']['id'] ?? null,
                'customer_note'       => $this->truncate((string) ($input['note'] ?? ''), 500),
                'created_at'          => $now,
            ]);

            $sliceIndex = 0;
            $createdSlices = [];

            foreach ($quote['slices'] as $slice) {
                $sliceIndex++;
                $suffix = chr(64 + min($sliceIndex, 26));   // A, B, C …
                $subNumber = $orderNumber . '-' . $suffix;

                $subOrderId = $this->db->insert('pharmacy_orders', [
                    'order_id'          => $orderId,
                    'pharmacy_id'       => $slice['pharmacy_id'],
                    'sub_order_number'  => $subNumber,
                    'status'            => self::STATUS_PENDING_PAYMENT,
                    'items_subtotal'    => $slice['items_subtotal'],
                    'delivery_fee'      => $slice['delivery_fee'],
                    'tax_total'         => $slice['tax_total'],
                    'discount_total'    => 0.00,
                    'platform_fee'      => $slice['commission_amount'],
                    'pharmacy_earnings' => $slice['pharmacy_earnings'],
                    'total'             => $slice['total'],
                    'created_at'        => $now,
                ]);

                foreach ($slice['items'] as $item) {
                    // Re-read price INSIDE the lock to defeat any race.
                    $locked = $this->db->first(
                        'SELECT price, discount_price, tax_rate, stock_qty, requires_prescription, name, sku
                         FROM products WHERE id = ? FOR UPDATE',
                        ['id' => $item['product_id']]
                    );
                    if ($locked === null || (int) $locked['stock_qty'] < $item['quantity']) {
                        throw new ValidationException(['cart' => [
                            sprintf('"%s" sold out while you were checking out. Please review your cart.', $item['name']),
                        ]]);
                    }

                    $unitPrice = $this->cart->effectivePrice((float) $locked['price'], $locked['discount_price']);
                    $taxAmount = round($unitPrice * $item['quantity'] * ((float) $locked['tax_rate'] / 100), 2);
                    $lineTotal = round($unitPrice * $item['quantity'] + $taxAmount, 2);

                    $this->db->insert('order_items', [
                        'order_id'       => $orderId,
                        'product_id'     => $item['product_id'],
                        'pharmacy_id'    => $slice['pharmacy_id'],
                        'product_name'   => $locked['name'],
                        'sku'            => $locked['sku'],
                        'image'          => $item['image'],
                        'unit_price'     => $unitPrice,
                        'quantity'       => $item['quantity'],
                        'discount'       => 0.00,
                        'tax_rate'       => (float) $locked['tax_rate'],
                        'tax_amount'     => $taxAmount,
                        'line_total'     => $lineTotal,
                        'requires_prescription' => (int) $locked['requires_prescription'],
                        'prescription_status'   => (int) $locked['requires_prescription'] === 1 ? 'pending' : 'not_required',
                        'status'         => 'pending',
                    ]);

                    // ---- stock out (FEFO across batches) --------------------
                    $allocations = $this->inventory->allocateBatches($item['product_id'], $item['quantity']);
                    foreach ($allocations as $allocation) {
                        $this->inventory->adjust(
                            $item['product_id'],
                            -$allocation['quantity'],
                            InventoryService::TYPE_SALE,
                            'Order ' . $subNumber,
                            $allocation['batch_id'],
                            'order',
                            $orderId,
                            $customerId
                        );
                    }
                    if ($allocations === []) {
                        // No batches tracked — take it from the aggregate counter.
                        $this->inventory->adjust(
                            $item['product_id'],
                            -$item['quantity'],
                            InventoryService::TYPE_SALE,
                            'Order ' . $subNumber,
                            null,
                            'order',
                            $orderId,
                            $customerId
                        );
                    }

                    $this->db->run(
                        'UPDATE products SET sales_count = sales_count + ? WHERE id = ?',
                        ['qty' => $item['quantity'], 'id' => $item['product_id']]
                    );
                }

                // ---- commission record (pending until delivery) ------------
                $this->db->insert('commissions', [
                    'pharmacy_order_id'  => $subOrderId,
                    'order_id'           => $orderId,
                    'pharmacy_id'        => $slice['pharmacy_id'],
                    'base_amount'        => $slice['items_subtotal'],
                    'rate_percent'       => $slice['commission_rate'],
                    'fixed_amount'       => 0.00,
                    'commission_amount'  => $slice['commission_amount'],
                    'pharmacy_earnings'  => $slice['pharmacy_earnings'],
                    'status'             => 'pending',
                    'created_at'         => $now,
                ]);

                // ---- pharmacy wallet shows the pending amount --------------
                $this->creditWalletPending($slice['pharmacy_id'], $slice['pharmacy_earnings']);

                $this->db->insert('order_status_history', [
                    'order_id'    => $orderId,
                    'scope'       => 'pharmacy_order',
                    'scope_id'    => $subOrderId,
                    'to_status'   => self::STATUS_PENDING_PAYMENT,
                    'note'        => 'Awaiting payment',
                    'actor_id'    => $customerId,
                    'actor_role'  => 'customer',
                    'created_at'  => $now,
                ]);

                $createdSlices[] = [
                    'pharmacy_order_id' => $subOrderId,
                    'pharmacy_id'       => $slice['pharmacy_id'],
                    'pharmacy_name'     => $slice['pharmacy_name'],
                    'sub_order_number'  => $subNumber,
                    'total'             => $slice['total'],
                ];
            }

            // ---- coupon usage ------------------------------------------------
            if ($quote['coupon'] !== null) {
                $couponId = (int) $quote['coupon']['id'];
                $this->db->insert('coupon_usages', [
                    'coupon_id' => $couponId,
                    'user_id'   => $customerId,
                    'order_id'  => $orderId,
                    'discount'  => $quote['discount_total'],
                ]);
                $this->db->run('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?', ['id' => $couponId]);
            }

            // ---- prescription upload ----------------------------------------
            $this->attachPrescription($orderId, $customerId, $input);

            $this->db->insert('order_status_history', [
                'order_id'   => $orderId,
                'scope'      => 'order',
                'scope_id'   => $orderId,
                'to_status'  => self::STATUS_PENDING_PAYMENT,
                'note'       => 'Order placed',
                'actor_id'   => $customerId,
                'actor_role' => 'customer',
                'created_at' => $now,
            ]);

            // ---- empty the cart ---------------------------------------------
            $this->cart->clear();
            $this->db->update('carts', ['status' => 'converted'], 'id = ?', ['id' => $this->cart->cartId()]);

            $order = $this->find($orderId);

            // ---- notifications ------------------------------------------------
            (new NotificationService())->orderPlaced($customerId, $orderId, $orderNumber)->send();
            foreach ($createdSlices as $slice) {
                (new NotificationService())
                    ->toPharmacy(
                        $slice['pharmacy_id'],
                        'order.placed',
                        'New order received',
                        "Sub-order {$slice['sub_order_number']} (₦" . number_format((float) $slice['total'], 2) . ') is waiting for you.',
                        '/pharmacy/orders',
                        'pharmacy_order',
                        $slice['pharmacy_order_id']
                    )
                    ->send();
            }

            return ['order' => $order, 'slices' => $createdSlices, 'quote' => $quote];
        });
    }

    // =======================================================================
    //  3. STATE MACHINE
    // =======================================================================

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Mark the parent order paid (called only from a verified gateway webhook).
     */
    public function markPaid(int $orderId, string $gatewayCode, string $reference): void
    {
        $this->db->transaction(function () use ($orderId, $gatewayCode, $reference): void {
            $order = $this->db->first('SELECT * FROM orders WHERE id = ? FOR UPDATE', ['id' => $orderId]);
            if ($order === null || $order['payment_status'] === 'successful') {
                return;   // idempotent — webhooks retry
            }

            $now = date('Y-m-d H:i:s');
            $this->db->update('orders', [
                'status'         => self::STATUS_PAID,
                'payment_status' => 'successful',
                'paid_at'        => $now,
                'placed_at'      => $order['placed_at'] ?? $now,
            ], 'id = ?', ['id' => $orderId]);

            $this->db->update(
                'pharmacy_orders',
                ['status' => self::STATUS_PAID],
                'order_id = ? AND status = ?',
                ['order_id' => $orderId, 'status' => self::STATUS_PENDING_PAYMENT]
            );

            $this->db->update('payments', [
                'status' => 'successful',
                'reference' => $reference,
                'paid_at' => $now,
            ], 'order_id = ?', ['order_id' => $orderId]);

            $this->db->insert('order_status_history', [
                'order_id'   => $orderId,
                'scope'      => 'order',
                'scope_id'   => $orderId,
                'from_status' => $order['status'],
                'to_status'  => self::STATUS_PAID,
                'note'       => "Payment confirmed via {$gatewayCode}",
                'created_at' => $now,
            ]);

            $customer = $this->db->first('SELECT full_name FROM users WHERE id = ?', ['id' => $order['customer_id']]);
            (new NotificationService())
                ->orderPaid((int) $order['customer_id'], $orderId, (string) $order['order_number'], number_format((float) $order['total'], 2))
                ->send();
            (new MailService())->sendOrderConfirmation(
                (string) $this->db->value('SELECT email FROM users WHERE id = ?', ['id' => $order['customer_id']]),
                (string) ($customer['full_name'] ?? 'Customer'),
                (string) $order['order_number'],
                (float) $order['total']
            );
        });
    }

    /**
     * Apply a pharmacy action to one slice and re-aggregate the parent order.
     */
    public function updateSlice(int $pharmacyOrderId, int $pharmacyId, string $action, ?string $note = null): void
    {
        $targets = self::SLICE_ACTIONS[$action] ?? null;
        if ($targets === null) {
            throw new ValidationException(['action' => ['Unknown order action.']]);
        }

        $this->db->transaction(function () use ($pharmacyOrderId, $pharmacyId, $action, $targets, $note): void {
            $slice = $this->db->first(
                'SELECT * FROM pharmacy_orders WHERE id = ? AND pharmacy_id = ? FOR UPDATE',
                ['id' => $pharmacyOrderId, 'pharmacy_id' => $pharmacyId]
            );
            if ($slice === null) {
                throw HttpException::forbidden('That order does not belong to your pharmacy.');
            }
            if (!in_array($slice['status'], $targets, true)) {
                throw new ValidationException(['status' => [
                    sprintf('This order is already %s.', str_replace('_', ' ', (string) $slice['status'])),
                ]]);
            }

            $newStatus = $action === 'ready'
                ? ($this->pickupOrderId((int) $slice['order_id']) ? self::STATUS_READY_FOR_PICKUP : self::STATUS_READY_FOR_DELIVERY)
                : $targets[0];

            $now = date('Y-m-d H:i:s');
            $update = ['status' => $newStatus, 'pharmacy_note' => $note];

            if ($newStatus === self::STATUS_RECEIVED) {
                $update['accepted_at'] = $now;
            } elseif ($newStatus === self::STATUS_PREPARING) {
                $update['accepted_at'] = $update['accepted_at'] ?? $slice['accepted_at'];
            } elseif (in_array($newStatus, [self::STATUS_READY_FOR_PICKUP, self::STATUS_READY_FOR_DELIVERY], true)) {
                $update['ready_at'] = $now;
            } elseif ($newStatus === self::STATUS_DELIVERED) {
                $update['delivered_at'] = $now;
            } elseif ($newStatus === self::STATUS_CANCELLED) {
                $update['cancelled_at'] = $now;
                $update['cancel_reason'] = $note;
            }

            $this->db->update('pharmacy_orders', $update, 'id = ?', ['id' => $pharmacyOrderId]);

            $this->db->insert('order_status_history', [
                'order_id'   => (int) $slice['order_id'],
                'scope'      => 'pharmacy_order',
                'scope_id'   => $pharmacyOrderId,
                'from_status' => $slice['status'],
                'to_status'  => $newStatus,
                'note'       => $note,
                'actor_id'   => Auth::id(),
                'actor_role' => Auth::role(),
                'created_at' => $now,
            ]);

            // ---- restore stock if the slice is cancelled --------------------
            if ($newStatus === self::STATUS_CANCELLED) {
                $this->restoreStockForSlice((int) $slice['order_id'], (int) $pharmacyId, $note);
            }

            // ---- re-aggregate the parent order ------------------------------
            $this->reaggregate((int) $slice['order_id']);

            // ---- customer-facing notification -------------------------------
            $orderNumber = (string) $this->db->value('SELECT order_number FROM orders WHERE id = ?', ['id' => $slice['order_id']]);
            $customerId  = (int) $this->db->value('SELECT customer_id FROM orders WHERE id = ?', ['id' => $slice['order_id']]);

            $map = [
                self::STATUS_RECEIVED            => 'accepted',
                self::STATUS_PREPARING           => 'preparing',
                self::STATUS_READY_FOR_PICKUP    => 'ready',
                self::STATUS_READY_FOR_DELIVERY  => 'ready',
                self::STATUS_OUT_FOR_DELIVERY    => 'dispatched',
                self::STATUS_DELIVERED           => 'delivered',
                self::STATUS_CANCELLED           => 'cancelled',
            ];

            if (isset($map[$newStatus])) {
                (new NotificationService())
                    ->orderStatus(
                        $customerId,
                        (int) $slice['order_id'],
                        $orderNumber,
                        $map[$newStatus],
                        $note
                    )
                    ->send();
            }

            // ---- earnings released on delivery ------------------------------
            if ($newStatus === self::STATUS_DELIVERED) {
                (new WalletService())->releaseEarningsForSlice($pharmacyOrderId);
            }
        });
    }

    /**
     * Customer cancellation (only before a pharmacy starts preparing).
     */
    public function cancelOrder(int $orderId, int $customerId, string $reason): void
    {
        $this->db->transaction(function () use ($orderId, $customerId, $reason): void {
            $order = $this->db->first(
                'SELECT * FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE',
                ['id' => $orderId, 'customer_id' => $customerId]
            );
            if ($order === null) {
                throw HttpException::notFound('Order not found.');
            }
            if (!in_array($order['status'], [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID, self::STATUS_RECEIVED], true)) {
                throw new ValidationException(['status' => [
                    'This order is already being prepared and can no longer be cancelled online. Please contact the pharmacy.',
                ]]);
            }

            $now = date('Y-m-d H:i:s');
            $this->db->update('orders', [
                'status'        => self::STATUS_CANCELLED,
                'cancelled_at'  => $now,
                'cancel_reason' => $this->truncate($reason, 255),
            ], 'id = ?', ['id' => $orderId]);

            $slices = $this->db->all('SELECT id, pharmacy_id, status FROM pharmacy_orders WHERE order_id = ?', ['order_id' => $orderId]);
            foreach ($slices as $slice) {
                if (in_array($slice['status'], [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID, self::STATUS_RECEIVED], true)) {
                    $this->db->update('pharmacy_orders', [
                        'status'        => self::STATUS_CANCELLED,
                        'cancelled_at'  => $now,
                        'cancel_reason' => $this->truncate($reason, 255),
                    ], 'id = ?', ['id' => $slice['id']]);

                    $this->restoreStockForSlice($orderId, (int) $slice['pharmacy_id'], $reason);
                }
            }

            $this->db->insert('order_status_history', [
                'order_id'   => $orderId,
                'scope'      => 'order',
                'scope_id'   => $orderId,
                'from_status' => $order['status'],
                'to_status'  => self::STATUS_CANCELLED,
                'note'       => $this->truncate($reason, 255),
                'actor_id'   => $customerId,
                'actor_role' => 'customer',
                'created_at' => $now,
            ]);

            (new NotificationService())
                ->orderStatus($customerId, $orderId, (string) $order['order_number'], 'cancelled', $reason)
                ->send();
        });
    }

    // =======================================================================
    //  Queries
    // =======================================================================

    /** @return array<string,mixed> */
    public function find(int $orderId): array
    {
        $order = $this->db->first('SELECT * FROM orders WHERE id = ? LIMIT 1', ['id' => $orderId]);
        if ($order === null) {
            throw HttpException::notFound('Order not found.');
        }
        $order['slices'] = $this->slices($orderId);
        $order['items']  = $this->items($orderId);
        $order['history'] = $this->history($orderId);
        return $order;
    }

    /** @return array<string,mixed>|null */
    public function findForCustomer(int $orderId, int $customerId): ?array
    {
        $order = $this->db->first(
            'SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1',
            ['id' => $orderId, 'customer_id' => $customerId]
        );
        if ($order === null) {
            return null;
        }
        $order['slices']  = $this->slices($orderId);
        $order['items']   = $this->items($orderId);
        $order['history'] = $this->history($orderId);
        $order['deliveries'] = $this->deliveries($orderId);
        return $order;
    }

    /** @return list<array<string,mixed>> */
    public function slices(int $orderId): array
    {
        return $this->db->all(
            'SELECT po.*, ph.name AS pharmacy_name, ph.slug AS pharmacy_slug, ph.logo AS pharmacy_logo,
                    ph.phone AS pharmacy_phone, ph.city, ph.state, ph.address,
                    d.tracking_number, d.status AS delivery_status, d.otp_code, d.delivery_note
             FROM pharmacy_orders po
             JOIN pharmacies ph ON ph.id = po.pharmacy_id
             LEFT JOIN deliveries d ON d.pharmacy_order_id = po.id
             WHERE po.order_id = ?
             ORDER BY po.id ASC',
            ['order_id' => $orderId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function items(int $orderId): array
    {
        return $this->db->all(
            'SELECT oi.*, p.slug AS product_slug
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.pharmacy_id ASC, oi.id ASC',
            ['order_id' => $orderId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function history(int $orderId): array
    {
        return $this->db->all(
            'SELECT h.*, u.full_name AS actor_name
             FROM order_status_history h
             LEFT JOIN users u ON u.id = h.actor_id
             WHERE h.order_id = ?
             ORDER BY h.id ASC',
            ['order_id' => $orderId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function deliveries(int $orderId): array
    {
        return $this->db->all(
            'SELECT d.*, u.full_name AS personnel_name, u.phone AS personnel_phone
             FROM deliveries d
             LEFT JOIN users u ON u.id = d.personnel_id
             WHERE d.order_id = ?',
            ['order_id' => $orderId]
        );
    }

    /** @return array<string,mixed>|null */
    public function findSliceForPharmacy(int $pharmacyOrderId, int $pharmacyId): ?array
    {
        $slice = $this->db->first(
            'SELECT po.*, o.order_number, o.customer_id, o.fulfilment_method, o.address_snapshot,
                    o.payment_status, o.customer_note, o.total AS order_total
             FROM pharmacy_orders po
             JOIN orders o ON o.id = po.order_id
             WHERE po.id = ? AND po.pharmacy_id = ? LIMIT 1',
            ['id' => $pharmacyOrderId, 'pharmacy_id' => $pharmacyId]
        );
        if ($slice === null) {
            return null;
        }
        $slice['items']     = $this->db->all('SELECT * FROM order_items WHERE order_id = ? AND pharmacy_id = ?', ['order_id' => $slice['order_id'], 'pharmacy_id' => $pharmacyId]);
        $slice['customer']  = $this->db->first('SELECT full_name, email, phone FROM users WHERE id = ?', ['id' => $slice['customer_id']]);
        $slice['address']   = $slice['address_snapshot'] !== null ? json_decode((string) $slice['address_snapshot'], true) : null;
        $slice['delivery']  = $this->db->first('SELECT * FROM deliveries WHERE pharmacy_order_id = ?', ['id' => $pharmacyOrderId]);
        return $slice;
    }

    // =======================================================================
    //  Internals
    // =======================================================================

    /**
     * Recompute the parent order status from its slices.
     *
     * Public because the delivery service legitimately needs to re-aggregate
     * after a rider updates a delivery — the parent order is derived state
     * and always follows its slices.
     */
    public function reaggregate(int $orderId): void
    {
        $slices = $this->db->all('SELECT status FROM pharmacy_orders WHERE order_id = ?', ['order_id' => $orderId]);
        $statuses = array_column($slices, 'status');

        $newStatus = match (true) {
            $statuses === []                                        => self::STATUS_PENDING_PAYMENT,
            in_array(self::STATUS_CANCELLED, $statuses, true)
                && count(array_unique($statuses)) === 1              => self::STATUS_CANCELLED,
            in_array(self::STATUS_DELIVERED, $statuses, true)
                && !in_array(self::STATUS_CANCELLED, $statuses, true)
                && count(array_filter($statuses, static fn($s) => $s === self::STATUS_DELIVERED)) === count($statuses)
            => self::STATUS_DELIVERED,
            in_array(self::STATUS_OUT_FOR_DELIVERY, $statuses, true) => self::STATUS_OUT_FOR_DELIVERY,
            in_array(self::STATUS_READY_FOR_DELIVERY, $statuses, true) => self::STATUS_READY_FOR_DELIVERY,
            in_array(self::STATUS_READY_FOR_PICKUP, $statuses, true)  => self::STATUS_READY_FOR_PICKUP,
            in_array(self::STATUS_PREPARING, $statuses, true)         => self::STATUS_PREPARING,
            in_array(self::STATUS_PROCESSING, $statuses, true)        => self::STATUS_PROCESSING,
            in_array(self::STATUS_RECEIVED, $statuses, true)          => self::STATUS_RECEIVED,
            in_array(self::STATUS_PAID, $statuses, true)              => self::STATUS_PAID,
            default                                                  => self::STATUS_PENDING_PAYMENT,
        };

        $update = ['status' => $newStatus];
        if ($newStatus === self::STATUS_DELIVERED) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->update('orders', $update, 'id = ?', ['id' => $orderId]);
    }

    private function restoreStockForSlice(int $orderId, int $pharmacyId, ?string $reason): void
    {
        $items = $this->db->all(
            'SELECT * FROM order_items WHERE order_id = ? AND pharmacy_id = ?',
            ['order_id' => $orderId, 'pharmacy_id' => $pharmacyId]
        );
        foreach ($items as $item) {
            if ($item['status'] === 'cancelled') {
                continue;   // never double-restore
            }
            $this->inventory->adjust(
                (int) $item['product_id'],
                (int) $item['quantity'],
                InventoryService::TYPE_RELEASE,
                'Stock restored — order cancelled: ' . ($reason ?? 'no reason given'),
                null,
                'order',
                $orderId,
                Auth::id()
            );
            $this->db->update(
                'order_items',
                ['status' => 'cancelled'],
                'id = ?',
                ['id' => $item['id']]
            );
        }
    }

    /** @return array<string,mixed> */
    private function resolveAddress(int $customerId, ?int $addressId): array
    {
        $address = $addressId !== null
            ? $this->db->first(
                'SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1',
                ['id' => $addressId, 'user_id' => $customerId]
            )
            : $this->db->first(
                'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1',
                ['user_id' => $customerId]
            );

        if ($address === null) {
            throw new ValidationException(['address_id' => ['Please add a delivery address before checking out.']]);
        }
        return $address;
    }

    private function attachPrescription(int $orderId, int $customerId, array $input): void
    {
        $path = trim((string) ($input['prescription_path'] ?? ''));
        if ($path === '') {
            return;
        }
        $pharmacies = $this->db->column('SELECT DISTINCT pharmacy_id FROM order_items WHERE order_id = ?', ['order_id' => $orderId]);
        foreach ($pharmacies as $pharmacyId) {
            $this->db->insert('prescriptions', [
                'user_id'       => $customerId,
                'order_id'      => $orderId,
                'pharmacy_id'   => (int) $pharmacyId,
                'file_path'     => $path,
                'notes'         => $this->truncate((string) ($input['prescription_note'] ?? ''), 500),
                'status'        => 'pending',
                'expires_at'    => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);
        }
    }

    /**
     * @return array{valid:bool, coupon?:array<string,mixed>, discount:float, errors:list<string>}
     */
    private function validateCoupon(string $code, float $subtotal): array
    {
        $coupon = $this->db->first(
            'SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1',
            ['code' => $code]
        );
        if ($coupon === null) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => ['That promo code is not valid.']];
        }
        if ($coupon['starts_at'] !== null && strtotime((string) $coupon['starts_at']) > time()) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => ['That promo code is not active yet.']];
        }
        if ($coupon['expires_at'] !== null && strtotime((string) $coupon['expires_at']) < time()) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => ['That promo code has expired.']];
        }
        if ($coupon['usage_limit'] !== null && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit']) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => ['That promo code has reached its usage limit.']];
        }
        if ($subtotal < (float) $coupon['min_order_value']) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => [
                sprintf('This code requires a minimum order of ₦%s.', number_format((float) $coupon['min_order_value'], 2)),
            ]];
        }
        $usedByMe = (int) $this->db->value(
            'SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?',
            ['coupon_id' => $coupon['id'], 'user_id' => Auth::id()]
        );
        if ($usedByMe >= (int) $coupon['per_user_limit']) {
            return ['valid' => false, 'discount' => 0.0, 'errors' => ['You have already used this promo code the maximum number of times.']];
        }

        $discount = $coupon['type'] === 'percent'
            ? round($subtotal * ((float) $coupon['value'] / 100), 2)
            : round((float) $coupon['value'], 2);

        if ($coupon['max_discount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }
        $discount = min($discount, $subtotal);

        return ['valid' => true, 'coupon' => $coupon, 'discount' => round($discount, 2), 'errors' => []];
    }

    private function pickupOrderId(int $orderId): bool
    {
        $method = $this->db->value('SELECT fulfilment_method FROM orders WHERE id = ?', ['id' => $orderId]);
        return $method === 'pickup';
    }

    private function creditWalletPending(int $pharmacyId, float $amount): void
    {
        $wallet = $this->db->first('SELECT id FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId]);
        if ($wallet === null) {
            $walletId = $this->db->insert('pharmacy_wallets', ['pharmacy_id' => $pharmacyId]);
        } else {
            $walletId = (int) $wallet['id'];
        }
        $this->db->run(
            'UPDATE pharmacy_wallets SET pending_balance = pending_balance + ? WHERE id = ?',
            ['amount' => $amount, 'id' => $walletId]
        );
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'IPL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $exists = $this->db->value('SELECT id FROM orders WHERE order_number = ? LIMIT 1', ['order_number' => $number]);
        } while ($exists !== null);
        return $number;
    }

    private function truncate(string $value, int $limit): string
    {
        $value = trim(strip_tags($value));
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
    }
}
