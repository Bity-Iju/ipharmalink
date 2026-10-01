<?php

/**
 * iPharmaLink :: Notification service
 * ---------------------------------------------------------------------------
 * One fan-out point for user notifications. In-app rows are written always;
 * email and SMS go through pluggable channels so a real provider can be
 * dropped in without touching business code.
 *
 *   $notifications->toCustomer($id)->orderPaid($order);
 *   $notifications->toPharmacyStaff($pharmacyId)->lowStock($product);
 */

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Setting;
use App\Session;
use App\View;

final class NotificationService
{
    private const EVENT_ICONS = [
        'order.placed'        => 'bi-bag-check',
        'order.paid'          => 'bi-credit-card',
        'order.accepted'      => 'bi-check2-circle',
        'order.preparing'     => 'bi-hourglass-split',
        'order.ready'         => 'bi-box2-heart',
        'order.dispatched'    => 'bi-truck',
        'order.delivered'     => 'bi-check2-all',
        'order.cancelled'     => 'bi-x-circle',
        'order.refunded'      => 'bi-arrow-counterclockwise',
        'payment.success'     => 'bi-cash-coin',
        'payment.failed'      => 'bi-exclamation-triangle',
        'stock.low'           => 'bi-box-seam',
        'stock.expiring'      => 'bi-calendar-event',
        'pharmacy.approved'   => 'bi-patch-check',
        'pharmacy.suspended'  => 'bi-slash-circle',
        'prescription.approved' => 'bi-file-earmark-medical',
        'prescription.rejected' => 'bi-file-earmark-x',
        'payout.processed'    => 'bi-bank',
    ];

    /** @var list<array{userId:int,title:string,body:string,type:string,link:?string,entityType:?string,entityId:?int}> */
    private array $queue = [];

    // -----------------------------------------------------------------------
    //  Enqueue (in-request batching — one write per user, not per event)
    // -----------------------------------------------------------------------

    public function to(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        $this->queue[] = [
            'userId'     => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'link'       => $link,
            'entityType' => $entityType,
            'entityId'   => $entityId,
        ];
        return $this;
    }

    /**
     * Every user attached to a pharmacy (owner + staff).
     *
     * @return list<int>
     */
    public function pharmacyAudience(int $pharmacyId): array
    {
        $rows = Database::instance()->all(
            'SELECT owner_id AS user_id FROM pharmacies WHERE id = :id
             UNION
             SELECT user_id FROM pharmacy_staff WHERE pharmacy_id = :id2 AND status = \'active\'',
            ['id' => $pharmacyId, 'id2' => $pharmacyId]
        );
        return array_map(static fn(array $r): int => (int) $r['user_id'], $rows);
    }

    /** @return list<int> */
    public function adminIds(): array
    {
        return array_map('intval', Database::instance()->column(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.name = \'super_admin\' AND u.status = \'active\''
        ));
    }

    public function toPharmacy(
        int $pharmacyId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        foreach ($this->pharmacyAudience($pharmacyId) as $userId) {
            $this->to($userId, $type, $title, $body, $link, $entityType, $entityId);
        }
        return $this;
    }

    // -----------------------------------------------------------------------
    //  Domain events
    // -----------------------------------------------------------------------

    public function orderPlaced(int $userId, int $orderId, string $orderNumber): self
    {
        return $this->to($userId, 'order.placed', 'Order placed', "Your order {$orderNumber} has been received.", '/account/orders/' . $orderId, 'order', $orderId);
    }

    public function orderPaid(int $userId, int $orderId, string $orderNumber, string $amount): self
    {
        return $this->to($userId, 'order.paid', 'Payment successful', "We received your payment of ₦{$amount} for order {$orderNumber}.", '/account/orders/' . $orderId, 'order', $orderId);
    }

    public function orderStatus(int $userId, int $orderId, string $orderNumber, string $status, ?string $note = null): self
    {
        $title = match ($status) {
            'received'          => 'Pharmacy accepted your order',
            'preparing'         => 'Your order is being prepared',
            'ready_for_pickup'  => 'Your order is ready for pickup',
            'ready_for_delivery' => 'Your order is ready for delivery',
            'out_for_delivery'  => 'Your order is out for delivery',
            'delivered'         => 'Order delivered',
            'cancelled'         => 'Order cancelled',
            'refunded'          => 'Refund processed',
            default             => 'Order update',
        };
        return $this->to($userId, 'order.' . $status, $title, $note ?? "Order {$orderNumber} is now: " . ucfirst(str_replace('_', ' ', $status)) . '.', '/account/orders/' . $orderId, 'order', $orderId);
    }

    public function newOrderForPharmacy(int $pharmacyId, string $subOrderNumber, int $userId): self
    {
        return $this->toPharmacy($pharmacyId, 'order.placed', 'New order received', "Sub-order {$subOrderNumber} needs your attention.", '/pharmacy/orders', 'pharmacy_order', $userId);
    }

    public function lowStock(int $userId, int $productId, string $productName, int $remaining): self
    {
        return $this->to($userId, 'stock.low', 'Low stock alert', "\"{$productName}\" has only {$remaining} units left.", '/pharmacy/products/edit/' . $productId, 'product', $productId);
    }

    public function expiringSoon(int $userId, int $productId, string $productName, string $expiryDate): self
    {
        return $this->to($userId, 'stock.expiring', 'Product expiring soon', "\"{$productName}\" expires on {$expiryDate}.", '/pharmacy/inventory?expiring=1', 'product', $productId);
    }

    public function pharmacyDecision(int $userId, int $pharmacyId, string $status, ?string $reason = null): self
    {
        $title = match ($status) {
            'approved'  => 'Your pharmacy has been approved',
            'suspended' => 'Your pharmacy has been suspended',
            'rejected'  => 'Pharmacy registration not approved',
            default     => 'Pharmacy status updated',
        };
        return $this->to($userId, 'pharmacy.' . $status, $title, $reason, '/pharmacy/dashboard', 'pharmacy', $pharmacyId);
    }

    public function prescriptionDecision(int $userId, int $orderId, string $status, ?string $note = null): self
    {
        $approved = $status === 'approved';
        return $this->to(
            $userId,
            $approved ? 'prescription.approved' : 'prescription.rejected',
            $approved ? 'Prescription approved' : 'Prescription needs attention',
            $note ?? ($approved ? 'A pharmacist approved your prescription. Your order can now proceed.' : 'A pharmacist could not approve your prescription. Please contact the pharmacy.'),
            '/account/orders/' . $orderId,
            'prescription',
            $orderId
        );
    }

    // -----------------------------------------------------------------------
    //  Flush
    // -----------------------------------------------------------------------

    /** Persist everything queued so far, then optionally send off-platform. */
    public function send(bool $email = true, bool $sms = false): int
    {
        if ($this->queue === []) {
            return 0;
        }

        $sent = 0;
        try {
            $sent = Database::instance()->transaction(function () use ($email, $sms): int {
                $count = 0;
                foreach ($this->queue as $item) {
                    $id = Database::instance()->insert('notifications', [
                        'user_id'     => $item['userId'],
                        'type'        => $item['type'],
                        'title'       => $item['title'],
                        'body'        => $item['body'],
                        'link'        => $item['link'],
                        'entity_type' => $item['entityType'],
                        'entity_id'   => $item['entityId'],
                        'channel'     => 'inapp',
                    ]);
                    $count++;
                }
                return $count;
            });
        } catch (\Throwable $e) {
            \App\Logger::error('Notification persistence failed: ' . $e->getMessage());
        }

        if ($email || $sms) {
            $this->dispatchOffPlatform($email, $sms);
        }

        $this->queue = [];
        return $sent;
    }

    // -----------------------------------------------------------------------
    //  Reading
    // -----------------------------------------------------------------------

    public static function unreadCount(int $userId): int
    {
        return (int) Database::instance()->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            ['id' => $userId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return Database::instance()->all(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}",
            ['id' => $userId]
        );
    }

    public static function markRead(int $userId, int $notificationId): void
    {
        Database::instance()->run(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }

    public static function markAllRead(int $userId): int
    {
        return Database::instance()->run(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
            ['user_id' => $userId]
        )->rowCount();
    }

    public static function icon(string $type): string
    {
        return self::EVENT_ICONS[$type] ?? 'bi-bell';
    }

    public static function toTemplate(): string
    {
        $userId = \App\Auth::id();
        if ($userId === null) {
            return '';
        }
        return View::capture('components/notification-bell', ['userId' => $userId]);
    }

    // -----------------------------------------------------------------------

    private function dispatchOffPlatform(bool $email, bool $sms): void
    {
        $db = Database::instance();

        foreach ($this->queue as $item) {
            if ($email) {
                $user = $db->first('SELECT email, full_name FROM users WHERE id = ? LIMIT 1', ['id' => $item['userId']]);
                if ($user !== null) {
                    (new MailService())->send($user['email'], $item['title'], $item['body'] ?? '', $user['full_name']);
                }
            }
            if ($sms) {
                $user = $db->first('SELECT phone FROM users WHERE id = ? LIMIT 1', ['id' => $item['userId']]);
                if ($user !== null && $user['phone'] !== null) {
                    (new SmsService())->send($user['phone'], $item['title'] . ' — ' . ($item['body'] ?? ''));
                }
            }
        }
    }
}
