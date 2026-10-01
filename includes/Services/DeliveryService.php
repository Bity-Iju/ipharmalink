<?php

/**
 * iPharmaLink :: Delivery service
 * ---------------------------------------------------------------------------
 * One delivery row per (order, pharmacy) slice — a three-pharmacy order is
 * three separate deliveries, each dispatched and tracked independently.
 *
 * Status machine:
 *   pending_assignment -> assigned -> picked_up -> in_transit -> delivered
 *                                                      \-> failed_delivery
 *   any -> cancelled
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\HttpException;
use App\ValidationException;

final class DeliveryService
{
    public const STATUS_PENDING_ASSIGNMENT = 'pending_assignment';
    public const STATUS_ASSIGNED            = 'assigned';
    public const STATUS_PICKED_UP           = 'picked_up';
    public const STATUS_IN_TRANSIT          = 'in_transit';
    public const STATUS_DELIVERED           = 'delivered';
    public const STATUS_FAILED              = 'failed_delivery';
    public const STATUS_CANCELLED           = 'cancelled';

    private const TRANSITIONS = [
        self::STATUS_PENDING_ASSIGNMENT => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_ASSIGNED            => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP           => [self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED, self::STATUS_FAILED],
        self::STATUS_IN_TRANSIT          => [self::STATUS_DELIVERED, self::STATUS_FAILED],
        self::STATUS_FAILED              => [self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED           => [],
        self::STATUS_CANCELLED           => [],
    ];

    /** Statuses that map onto a pharmacy slice action. */
    public const SLICE_STATUS = [
        'dispatch' => self::STATUS_IN_TRANSIT,
        'deliver'  => self::STATUS_DELIVERED,
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    // -----------------------------------------------------------------------
    //  Creation
    // -----------------------------------------------------------------------

    /** Create the delivery record for a slice once it is ready to dispatch. */
    public function createForSlice(int $pharmacyOrderId, int $pharmacyId, array $order, array $dropoff): int
    {
        $existing = $this->db->first(
            'SELECT id FROM deliveries WHERE order_id = ? AND pharmacy_id = ? LIMIT 1',
            ['order_id' => (int) $order['id'], 'pharmacy_id' => $pharmacyId]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $pharmacy = $this->db->first('SELECT address, latitude, longitude FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]) ?? [];

        return $this->db->insert('deliveries', [
            'order_id'          => (int) $order['id'],
            'pharmacy_order_id' => $pharmacyOrderId,
            'pharmacy_id'       => $pharmacyId,
            'status'            => self::STATUS_PENDING_ASSIGNMENT,
            'tracking_number'   => $this->trackingNumber(),
            'pickup_address'    => $pharmacy['address'] ?? null,
            'dropoff_address'   => json_encode($dropoff, JSON_UNESCAPED_SLASHES),
            'pickup_latitude'   => $pharmacy['latitude'] ?? null,
            'pickup_longitude'  => $pharmacy['longitude'] ?? null,
            'dropoff_latitude'  => $dropoff['latitude'] ?? null,
            'dropoff_longitude' => $dropoff['longitude'] ?? null,
            'distance_km'       => $this->distanceKm(
                (float) ($pharmacy['latitude'] ?? 0),
                (float) ($pharmacy['longitude'] ?? 0),
                (float) ($dropoff['latitude'] ?? 0),
                (float) ($dropoff['longitude'] ?? 0)
            ),
            'delivery_fee'      => (float) $order['delivery_fee'],
            'otp_code'          => (string) random_int(100000, 999999),
        ]);
    }

    // -----------------------------------------------------------------------
    //  Assignment
    // -----------------------------------------------------------------------

    public function assign(int $deliveryId, int $personnelId, ?int $pharmacyId = null): void
    {
        $this->db->transaction(function () use ($deliveryId, $personnelId, $pharmacyId): void {
            $delivery = $this->db->first('SELECT * FROM deliveries WHERE id = ? FOR UPDATE', ['id' => $deliveryId]);
            if ($delivery === null) {
                throw HttpException::notFound('Delivery not found.');
            }
            if (!in_array($delivery['status'], [self::STATUS_PENDING_ASSIGNMENT, self::STATUS_FAILED], true)) {
                throw new ValidationException(['status' => ['This delivery is already assigned.']]);
            }

            // A rider may only be attached to their own platform/pharmacy scope.
            $personnel = $this->db->first('SELECT * FROM delivery_personnel WHERE user_id = ? LIMIT 1', ['user_id' => $personnelId]);
            if ($personnel === null) {
                throw new ValidationException(['personnel' => ['That delivery person is not registered.']]);
            }
            if ((int) $personnel['is_available'] !== 1) {
                throw new ValidationException(['personnel' => ['That delivery person is not currently available.']]);
            }

            $this->db->update('deliveries', [
                'personnel_id' => $personnelId,
                'status'       => self::STATUS_ASSIGNED,
                'assigned_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', ['id' => $deliveryId]);

            $this->logStatus($deliveryId, $delivery['status'], self::STATUS_ASSIGNED, 'Rider assigned', $personnelId);
        });
    }

    /** @return list<array<string,mixed>> */
    public function availablePersonnel(?int $pharmacyId = null): array
    {
        if ($pharmacyId === null) {
            return $this->db->all(
                'SELECT dp.*, u.full_name, u.phone, u.profile_image
                 FROM delivery_personnel dp JOIN users u ON u.id = dp.user_id
                 WHERE dp.is_available = 1 AND u.status = \'active\'
                 ORDER BY dp.id ASC'
            );
        }
        return $this->db->all(
            'SELECT dp.*, u.full_name, u.phone, u.profile_image
             FROM delivery_personnel dp JOIN users u ON u.id = dp.user_id
             WHERE dp.is_available = 1 AND u.status = \'active\'
               AND (dp.pharmacy_id IS NULL OR dp.pharmacy_id = ?)
             ORDER BY dp.id ASC',
            ['pharmacy_id' => $pharmacyId]
        );
    }

    // -----------------------------------------------------------------------
    //  Status transitions
    // -----------------------------------------------------------------------

    /**
     * Advance a delivery. The rider must be the assigned person.
     *
     * @param array{gps_latitude?:float, gps_longitude?:float, note?:string, failure_reason?:string} $data
     */
    public function updateStatus(int $deliveryId, string $newStatus, array $data = [], ?int $actorId = null): void
    {
        $this->db->transaction(function () use ($deliveryId, $newStatus, $data, $actorId): void {
            $delivery = $this->db->first('SELECT * FROM deliveries WHERE id = ? FOR UPDATE', ['id' => $deliveryId]);
            if ($delivery === null) {
                throw HttpException::notFound('Delivery not found.');
            }
            if (!in_array($newStatus, self::TRANSITIONS[$delivery['status']] ?? [], true)) {
                throw new ValidationException(['status' => [
                    'That status change is not allowed from "' . str_replace('_', ' ', (string) $delivery['status']) . '".',
                ]]);
            }

            $actorId ??= Auth::id();

            // Authorisation: only the assigned rider (or a pharmacy admin) may move it.
            if (
                $actorId !== null && $delivery['personnel_id'] !== null
                && (int) $delivery['personnel_id'] !== $actorId && !Auth::isSuperAdmin()
            ) {
                $isOwningPharmacy = Auth::pharmacyId() !== null && Auth::pharmacyId() === (int) $delivery['pharmacy_id'];
                if (!$isOwningPharmacy) {
                    throw HttpException::forbidden('This delivery is assigned to someone else.');
                }
            }

            $update = ['status' => $newStatus];
            $note   = $data['note'] ?? null;

            if ($newStatus === self::STATUS_PICKED_UP) {
                $update['picked_up_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === self::STATUS_DELIVERED) {
                // Proof of delivery: the customer confirms with the OTP.
                $otp = trim((string) ($data['otp'] ?? ''));
                if ($delivery['otp_code'] !== null && $otp !== '') {
                    if (!hash_equals((string) $delivery['otp_code'], $otp)) {
                        throw new ValidationException(['otp' => ['The delivery code is incorrect.']]);
                    }
                }
                $update['delivered_at']              = date('Y-m-d H:i:s');
                $update['proof_image']               = $data['proof_image'] ?? $delivery['proof_image'];
                $update['signature_image']           = $data['signature_image'] ?? $delivery['signature_image'];
                $update['confirmed_by_customer_at']  = $otp !== '' ? date('Y-m-d H:i:s') : null;
            } elseif ($newStatus === self::STATUS_FAILED) {
                $update['failure_reason'] = substr((string) ($data['failure_reason'] ?? 'No reason provided'), 0, 255);
            }
            if ($note !== null) {
                $update['delivery_note'] = substr((string) $note, 0, 500);
            }

            $this->db->update('deliveries', $update, 'id = ?', ['id' => $deliveryId]);

            $this->logStatus(
                $deliveryId,
                (string) $delivery['status'],
                $newStatus,
                $note,
                $actorId,
                isset($data['gps_latitude']) ? (float) $data['gps_latitude'] : null,
                isset($data['gps_longitude']) ? (float) $data['gps_longitude'] : null
            );

            // ---- mirror onto the pharmacy slice and parent order -----------
            if (isset(self::SLICE_STATUS[$newStatus]) === false) {
                $this->syncOrderStatuses($deliveryId, $newStatus);
            }
            if (isset(self::SLICE_STATUS[$newStatus])) {
                $this->syncOrderStatuses($deliveryId, self::SLICE_STATUS[$newStatus]);
            }
        });
    }

    /** Customer-side confirmation using the OTP shown to the rider. */
    public function confirmByCustomer(int $deliveryId, int $customerId, string $otp): void
    {
        $delivery = $this->db->first(
            'SELECT d.*, o.customer_id FROM deliveries d
             JOIN orders o ON o.id = d.order_id
             WHERE d.id = ? AND o.customer_id = ? LIMIT 1',
            ['id' => $deliveryId, 'customer_id' => $customerId]
        );
        if ($delivery === null) {
            throw HttpException::notFound('Delivery not found.');
        }
        if ($delivery['otp_code'] === null || !hash_equals((string) $delivery['otp_code'], trim($otp))) {
            throw new ValidationException(['otp' => ['That confirmation code is incorrect.']]);
        }

        $this->updateStatus($deliveryId, self::STATUS_DELIVERED, [
            'otp'  => trim($otp),
            'note' => 'Confirmed by customer',
        ], $customerId);
    }

    // -----------------------------------------------------------------------
    //  Queries
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function find(int $deliveryId): ?array
    {
        $delivery = $this->db->first(
            'SELECT d.*, o.order_number, o.customer_id, ph.name AS pharmacy_name, ph.phone AS pharmacy_phone,
                    ph.address AS pharmacy_address, u.full_name AS personnel_name, u.phone AS personnel_phone
             FROM deliveries d
             JOIN orders o      ON o.id = d.order_id
             JOIN pharmacies ph ON ph.id = d.pharmacy_id
             LEFT JOIN users u  ON u.id = d.personnel_id
             WHERE d.id = ? LIMIT 1',
            ['id' => $deliveryId]
        );
        if ($delivery === null) {
            return null;
        }
        $delivery['customer']   = $this->db->first('SELECT full_name, phone, email FROM users WHERE id = ?', ['id' => $delivery['customer_id']]);
        $delivery['order_items'] = $this->db->all(
            'SELECT oi.* FROM order_items oi
             JOIN pharmacy_orders po ON po.order_id = oi.order_id AND po.pharmacy_id = oi.pharmacy_id
             WHERE po.id = ?',
            ['id' => $delivery['pharmacy_order_id']]
        );
        $delivery['history'] = $this->db->all(
            'SELECT * FROM delivery_status_history WHERE delivery_id = ? ORDER BY id ASC',
            ['delivery_id' => $deliveryId]
        );
        return $delivery;
    }

    /**
     * Paginated delivery list with role scoping.
     *
     * @param  array<string,mixed> $filters
     * @return Paginator
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 25): \App\Paginator
    {
        [$whereSql, $params] = $this->buildFilters($filters);

        return \App\Paginator::build(
            fn(Database $db): int => (int) $db->value("SELECT COUNT(*) FROM deliveries d{$whereSql}", $params),
            fn(Database $db, int $limit, int $offset): array => $db->all(
                "SELECT d.*, o.order_number, ph.name AS pharmacy_name, ph.address AS pharmacy_address,
                        ph.phone AS pharmacy_phone, u.full_name AS personnel_name, u.phone AS personnel_phone,
                        c.full_name AS customer_name, c.phone AS customer_phone
                 FROM deliveries d
                 JOIN orders o      ON o.id = d.order_id
                 JOIN pharmacies ph ON ph.id = d.pharmacy_id
                 JOIN users c       ON c.id = o.customer_id
                 LEFT JOIN users u  ON u.id = d.personnel_id
                 {$whereSql}
                 ORDER BY d.id DESC LIMIT {$limit} OFFSET {$offset}",
                $params
            ),
            $perPage,
            $page
        );
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['pharmacy_id'])) {
            $where[] = 'd.pharmacy_id = :pharmacy_id';
            $params['pharmacy_id'] = (int) $filters['pharmacy_id'];
        }
        if (!empty($filters['personnel_id'])) {
            $where[] = 'd.personnel_id = :personnel_id';
            $params['personnel_id'] = (int) $filters['personnel_id'];
        }
        if (!empty($filters['unassigned'])) {
            $where[] = 'd.personnel_id IS NULL';
        }
        if (!empty($filters['q'])) {
            $where[] = '(o.order_number LIKE :q OR ph.name LIKE :q OR c.full_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['from'])) {
            $where[] = 'd.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'd.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function logStatus(int $deliveryId, ?string $from, string $to, ?string $note, ?int $actorId, ?float $lat = null, ?float $lon = null): void
    {
        $this->db->insert('delivery_status_history', [
            'delivery_id' => $deliveryId,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $note,
            'actor_id'    => $actorId,
            'latitude'    => $lat,
            'longitude'   => $lon,
        ]);
    }

    private function syncOrderStatuses(int $deliveryId, string $deliveryStatus): void
    {
        $delivery = $this->db->first('SELECT order_id, pharmacy_order_id FROM deliveries WHERE id = ?', ['id' => $deliveryId]);
        if ($delivery === null || $delivery['pharmacy_order_id'] === null) {
            return;
        }

        $sliceStatus = match ($deliveryStatus) {
            self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT => OrderService::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_DELIVERED                          => OrderService::STATUS_DELIVERED,
            self::STATUS_CANCELLED                          => OrderService::STATUS_CANCELLED,
            default                                        => null,
        };
        if ($sliceStatus === null) {
            return;
        }

            $this->db->update('pharmacy_orders', ['status' => $sliceStatus], 'id = ?', ['id' => $delivery['pharmacy_order_id']]);
            $this->db->insert('order_status_history', [
                'order_id'   => (int) $delivery['order_id'],
                'scope'      => 'pharmacy_order',
                'scope_id'   => (int) $delivery['pharmacy_order_id'],
                'to_status'  => $sliceStatus,
                'note'       => 'Delivery ' . str_replace('_', ' ', $deliveryStatus),
                'actor_id'   => Auth::id(),
                'actor_role' => Auth::role(),
            ]);

            // The parent order is derived state and always follows its slices.
            (new OrderService($this->db))->reaggregate((int) $delivery['order_id']);
        }

    private function trackingNumber(): string
    {
        do {
            $number = 'IPD-' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $exists = $this->db->value('SELECT id FROM deliveries WHERE tracking_number = ? LIMIT 1', ['tracking_number' => $number]);
        } while ($exists !== null);
        return $number;
    }

    /** Great-circle distance in kilometres. */
    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): ?float
    {
        if ($lat1 == 0.0 || $lon1 == 0.0 || $lat2 == 0.0 || $lon2 == 0.0) {
            return null;
        }
        $earthRadius = 6371.0;
        $dLat        = deg2rad($lat2 - $lat1);
        $dLon        = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return round($earthRadius * 2 * asin(min(1, sqrt($a))), 2);
    }
}
