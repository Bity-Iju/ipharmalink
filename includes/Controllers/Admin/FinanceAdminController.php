<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\WalletService;
use App\Session;

/**
 * Admin finance desk: payments, refunds, commissions, wallets, payouts and
 * coupons.
 *
 * Money movement is always delegated to WalletService so the ledger stays the
 * single source of truth — the admin screens never write balances directly.
 */
final class FinanceAdminController extends Controller
{
    private const PER_PAGE = 20;

    // -----------------------------------------------------------------------
    //  GET /admin/payments
    // -----------------------------------------------------------------------
    public function payments(Request $request): void
    {
        $db      = Database::instance();
        $status  = $request->trimmed('status');
        $gateway = $request->trimmed('gateway');
        $search  = $request->trimmed('q');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]         = 'pay.status = :status';
            $params['status'] = $status;
        }
        if ($gateway !== '') {
            $where[]            = 'pay.gateway_code = :gateway';
            $params['gateway'] = $gateway;
        }
        if ($search !== '') {
            $where[] = '(o.order_number LIKE :q OR pay.reference LIKE :q2 OR u.email LIKE :q3)';
            $params += ['q' => '%' . $search . '%', 'q2' => '%' . $search . '%', 'q3' => '%' . $search . '%'];
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $from     = 'FROM payments pay
                    JOIN orders o ON o.id = pay.order_id
                    JOIN users  u ON u.id = pay.customer_id';

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) $from WHERE $whereSql", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $from, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT pay.id, pay.order_id, pay.gateway_code, pay.amount, pay.status,
                            pay.reference, pay.paid_at, pay.created_at,
                            o.order_number, u.full_name AS customer_name, u.email
                     $from
                     WHERE $whereSql
                     ORDER BY pay.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $stats = [
            'total'       => (int) $db->value('SELECT COUNT(*) FROM payments'),
            'successful'  => (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'successful'"),
            'failed'      => (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'failed'"),
            'refunded'    => (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'refunded'"),
            'volume'      => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful'"),
            'gateways'    => $db->all("SELECT DISTINCT gateway_code FROM payments WHERE gateway_code IS NOT NULL AND gateway_code <> '' ORDER BY gateway_code"),
        ];

        $this->view('admin/finance/payments', [
            'title'     => 'Payments',
            'paginator' => $paginator,
            'stats'     => $stats,
            'status'    => $status,
            'gateway'   => $gateway,
            'search'    => $search,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/refunds
    // -----------------------------------------------------------------------
    public function refunds(Request $request): void
    {
        $status  = $request->trimmed('status');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = $status !== '' ? 'WHERE r.status = :status' : '';
        $params = $status !== '' ? ['status' => $status] : [];

        $from = 'FROM refunds r
                 JOIN orders o ON o.id = r.order_id
                 JOIN users  u ON u.id = o.customer_id';

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) $from $where", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $from, $where, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT r.*, o.order_number, u.full_name AS customer_name
                     $from
                     $where
                     ORDER BY r.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $this->view('admin/finance/refunds', [
            'title'     => 'Refunds',
            'paginator' => $paginator,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/refunds/create
    // -----------------------------------------------------------------------
    public function createRefund(Request $request): void
    {
        $perPage = $this->perPage(50);
        $page    = $this->page();

        // Only paid orders that have not already been fully refunded.
        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                "SELECT COUNT(*)
                 FROM orders o
                 WHERE o.payment_status = 'successful'
                   AND (o.status NOT IN ('cancelled','refunded'))
                   AND o.total > (SELECT COALESCE(SUM(r.amount), 0) FROM refunds r WHERE r.order_id = o.id)"
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT o.id, o.order_number, o.total, o.created_at, u.full_name AS customer_name
                     FROM orders o
                     JOIN users u ON u.id = o.customer_id
                     WHERE o.payment_status = 'successful'
                       AND o.status NOT IN ('cancelled','refunded')
                       AND o.total > (SELECT COALESCE(SUM(r.amount), 0) FROM refunds r WHERE r.order_id = o.id)
                     ORDER BY o.created_at DESC
                     LIMIT $limit OFFSET $offset"
                );
            },
            $perPage,
            $page
        );

        $this->view('admin/finance/refund-create', [
            'title'     => 'Issue a refund',
            'paginator' => $paginator,
            'errors'    => [],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/refunds  (issued from the refund form)
    // -----------------------------------------------------------------------
    public function storeRefund(Request $request): void
    {
        $orderId = $request->int('order_id');
        $amount  = $request->float('amount');
        $reason  = $request->trimmed('reason');

        $order = Database::instance()->first(
            'SELECT id, order_number, total, payment_status FROM orders WHERE id = ?',
            ['id' => $orderId]
        );

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }
        if ($order['payment_status'] !== 'successful') {
            throw new HttpException(422, 'Only a paid order can be refunded.');
        }

        $alreadyRefunded = (float) Database::instance()->value(
            'SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE order_id = ?',
            ['order_id' => $orderId]
        );

        $remaining = round((float) $order['total'] - $alreadyRefunded, 2);
        $amount    = $amount > 0 ? min($amount, $remaining) : $remaining;

        if ($amount <= 0) {
            throw new HttpException(422, 'That order has already been fully refunded.');
        }

        $payment = Database::instance()->first(
            "SELECT id FROM payments WHERE order_id = ? AND status = 'successful' ORDER BY id DESC LIMIT 1",
            ['order_id' => $orderId]
        );

        $fully = $amount >= $remaining;

        Database::instance()->transaction(static function (Database $db) use ($order, $orderId, $payment, $amount, $reason, $fully): void {
            $refundId = $db->insert('refunds', [
                'order_id'     => $orderId,
                'payment_id'   => $payment['id'] ?? null,
                'amount'       => $amount,
                'reason'       => $reason,
                'status'       => 'completed',
                'processed_by' => (int) Auth::id(),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            if ($fully) {
                $db->update('orders', ['status' => 'refunded', 'payment_status' => 'refunded'], 'id = :id', ['id' => $orderId]);
                if (isset($payment['id'])) {
                    $db->update('payments', ['status' => 'refunded'], 'id = :id', ['id' => (int) $payment['id']]);
                }
            }

            (new AuditService())->log(
                'admin.refund.issued',
                'refund',
                $refundId,
                sprintf('Refund of %s on order %s', money($amount), $order['order_number'])
            );
        });

        Session::success(sprintf('Refund of %s recorded for order %s.', money($amount), $order['order_number']));
        Response::back('/admin/refunds');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/commissions
    // -----------------------------------------------------------------------
    public function commissions(Request $request): void
    {
        $db      = Database::instance();
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                "SELECT COUNT(*) FROM commissions c
                 JOIN pharmacy_orders po ON po.id = c.pharmacy_order_id
                 JOIN pharmacies ph       ON ph.id = c.pharmacy_id"
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT c.*, ph.name AS pharmacy_name, po.sub_order_number, po.status AS order_status
                     FROM commissions c
                     JOIN pharmacy_orders po ON po.id = c.pharmacy_order_id
                     JOIN pharmacies ph       ON ph.id = c.pharmacy_id
                     ORDER BY c.created_at DESC
                     LIMIT $limit OFFSET $offset"
                );
            },
            $perPage,
            $page
        );

        $totals = [
            'commission_earned'   => (float) $db->value("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE status = 'earned'"),
            'commission_pending'  => (float) $db->value("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE status = 'pending'"),
            'commission_reversed' => (float) $db->value("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE status = 'reversed'"),
            'pending_payouts'     => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE status IN ('requested','approved','processing')"),
        ];

        $this->view('admin/finance/commissions', [
            'title'     => 'Commissions',
            'paginator' => $paginator,
            'totals'    => $totals,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/wallets
    // -----------------------------------------------------------------------
    public function wallets(Request $request): void
    {
        $db      = Database::instance();
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                'SELECT COUNT(*) FROM pharmacy_wallets w JOIN pharmacies ph ON ph.id = w.pharmacy_id'
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT w.*, ph.name AS pharmacy_name, ph.status AS pharmacy_status
                     FROM pharmacy_wallets w
                     JOIN pharmacies ph ON ph.id = w.pharmacy_id
                     ORDER BY w.balance DESC
                     LIMIT $limit OFFSET $offset"
                );
            },
            $perPage,
            $page
        );

        $totals = [
            'available' => (float) $db->value('SELECT COALESCE(SUM(balance), 0) FROM pharmacy_wallets'),
            'pending'   => (float) $db->value('SELECT COALESCE(SUM(pending_balance), 0) FROM pharmacy_wallets'),
            'paid'      => (float) $db->value('SELECT COALESCE(SUM(total_paid), 0) FROM pharmacy_wallets'),
        ];

        $this->view('admin/finance/wallets', [
            'title'     => 'Wallets',
            'paginator' => $paginator,
            'totals'    => $totals,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/payouts
    // -----------------------------------------------------------------------
    public function payouts(Request $request): void
    {
        $status  = $request->trimmed('status');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = $status !== '' ? 'WHERE po.status = :status' : '';
        $params = $status !== '' ? ['status' => $status] : [];

        $from = 'FROM payouts po
                 JOIN pharmacies ph
                   ON ph.id = po.pharmacy_id
                   AND (ph.bank_account_number IS NULL OR ph.bank_account_number <> \'\')';

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) $from $where", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $from, $where, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT po.*, ph.name AS pharmacy_name,
                            ph.bank_name AS bank_name, ph.bank_account_number AS account_number
                     $from
                     $where
                     ORDER BY po.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $this->view('admin/finance/payouts', [
            'title'     => 'Payouts',
            'paginator' => $paginator,
            'status'    => $status,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /admin/payouts/{id}/status
    // -----------------------------------------------------------------------
    public function updatePayout(Request $request, array $params): void
    {
        $payoutId = (int) $this->param('id', $params);
        $status   = $request->trimmed('status');
        $notes    = $request->trimmed('notes');
        $adminId  = (int) Auth::id();
        $wallet   = new WalletService();

        $payout = Database::instance()->first('SELECT * FROM payouts WHERE id = ?', ['id' => $payoutId]);
        if ($payout === null) {
            throw new HttpException(404, 'Payout request not found.');
        }

        // Route through the wallet so balances move atomically.
        match ($status) {
            'approved' => $wallet->approvePayout($payoutId, $adminId),
            'paid'     => $wallet->markPayoutPaid($payoutId, $adminId, $notes !== '' ? $notes : null),
            'rejected' => $wallet->rejectPayout($payoutId, $adminId, $notes !== '' ? $notes : 'Rejected by finance'),
            default    => throw new HttpException(422, 'Choose a valid payout status.'),
        };

        (new AuditService($request))->logChange(
            'admin.payout.status_changed',
            'payout',
            $payoutId,
            ['status' => $payout['status']],
            ['status' => $status, 'notes' => $notes]
        );

        Session::success('Payout ' . $payout['reference'] . ' marked as ' . $status . '.');
        Response::back('/admin/payouts');
    }

    // -----------------------------------------------------------------------
    //  Coupons
    // -----------------------------------------------------------------------

    /** GET /admin/coupons */
    public function coupons(Request $request): void
    {
        $this->view('admin/finance/coupons', [
            'title'   => 'Coupons',
            'coupons' => Database::instance()->all(
                'SELECT c.*, ph.name AS pharmacy_name,
                        (SELECT COUNT(*) FROM coupon_usages cu WHERE cu.coupon_id = c.id) AS uses
                 FROM coupons c
                 LEFT JOIN pharmacies ph ON ph.id = c.pharmacy_id
                 ORDER BY c.is_active DESC, c.created_at DESC'
            ),
            'errors'  => [],
        ], 'layouts/dashboard');
    }

    /** POST /admin/coupons */
    public function storeCoupon(Request $request): void
    {
        $data = $this->couponInput($request);
        $id   = Database::instance()->insert('coupons', $data);

        (new AuditService($request))->log('admin.coupon.created', 'coupon', $id, $data);

        Session::success('Coupon created.');
        Response::back('/admin/coupons');
    }

    /** POST /admin/coupons/{id} */
    public function updateCoupon(Request $request, array $params): void
    {
        $id      = (int) $this->param('id', $params);
        $coupon  = Database::instance()->first('SELECT * FROM coupons WHERE id = ?', ['id' => $id]);
        if ($coupon === null) {
            throw new HttpException(404, 'Coupon not found.');
        }

        $data   = $this->couponInput($request);
        $before = $coupon;
        Database::instance()->update('coupons', $data, 'id = :id', ['id' => $id]);

        (new AuditService($request))->logChange('admin.coupon.updated', 'coupon', $id, $before, $data);

        Session::success('Coupon updated.');
        Response::back('/admin/coupons');
    }

    /** POST /admin/coupons/{id}/delete */
    public function destroyCoupon(Request $request, array $params): void
    {
        $id = (int) $this->param('id', $params);
        Database::instance()->delete('coupons', 'id = :id', ['id' => $id]);

        (new AuditService($request))->log('admin.coupon.deleted', 'coupon', $id, 'Coupon deleted');

        Session::success('Coupon deleted.');
        Response::back('/admin/coupons');
    }

    // -----------------------------------------------------------------------

    /**
     * Normalise and validate coupon input. Prices are never trusted from the
     * browser beyond type coercion — the server owns the numbers.
     *
     * @return array<string,mixed>
     */
    private function couponInput(Request $request): array
    {
        $type = $request->trimmed('type', 'percent');
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $type = 'percent';
        }

        return [
            'code'            => strtoupper($request->trimmed('code')),
            'description'     => $request->trimmed('description'),
            'type'            => $type,
            'value'           => max(0, $request->float('value')),
            'min_order_value' => max(0, $request->float('min_order_value')),
            'max_discount'    => max(0, $request->float('max_discount')),
            'usage_limit'     => $request->int('usage_limit') ?: null,
            'per_user_limit'  => $request->int('per_user_limit') ?: null,
            'pharmacy_id'     => $request->int('pharmacy_id') ?: null,
            'starts_at'       => $request->trimmed('starts_at') !== '' ? $request->trimmed('starts_at') : null,
            'expires_at'      => $request->trimmed('expires_at') !== '' ? $request->trimmed('expires_at') : null,
            'is_active'       => $request->bool('is_active') ? 1 : 0,
        ];
    }
}
