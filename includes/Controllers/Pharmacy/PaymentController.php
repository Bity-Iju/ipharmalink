<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\Paginator;
use App\Request;

/**
 * Pharmacy money movement — /pharmacy/payments
 *
 * Shows what the pharmacy has been paid, what it still owes, and every
 * movement through its wallet ledger. The pharmacy never sees platform
 * commission on other parties' sales.
 */
final class PaymentController extends Controller
{
    private const PER_PAGE = 25;

    /** GET /pharmacy/payments */
    public function index(Request $request): void
    {
        $db         = Database::instance();
        $pharmacyId = (int) Auth::pharmacyId();
        $tab        = $request->trimmed('tab', 'received');
        $search     = $request->trimmed('q');
        $perPage    = $this->perPage(self::PER_PAGE);
        $page       = $this->page();

        // ---- received: money paid to the pharmacy by customers ---------------
        $receivedSql = "SELECT pay.id, o.order_number, o.id AS order_id, pay.gateway_code,
                               pay.amount, pay.status, pay.reference, pay.paid_at, pay.created_at,
                               u.full_name AS customer_name
                        FROM payments pay
                        JOIN orders o ON o.id = pay.order_id
                        JOIN pharmacy_orders po ON po.order_id = o.id AND po.pharmacy_id = :pharmA
                        JOIN users u ON u.id = o.customer_id
                        WHERE 1=1";
        $receivedParams = ['pharmA' => $pharmacyId];

        if ($search !== '') {
            $receivedSql   .= ' AND (o.order_number LIKE :q OR pay.reference LIKE :q2 OR u.full_name LIKE :q3)';
            $receivedParams += [
                'q'  => '%' . $search . '%',
                'q2' => '%' . $search . '%',
                'q3' => '%' . $search . '%',
            ];
        }
        $receivedSql .= ' ORDER BY pay.created_at DESC';

        // ---- outstanding: pharmacy slices whose customer has not yet paid -----
        $outstandingSql = "SELECT po.id, po.sub_order_number, po.id AS order_id, po.total,
                                  po.pharmacy_earnings, po.status, po.created_at
                           FROM pharmacy_orders po
                           JOIN orders o ON o.id = po.order_id
                           WHERE po.pharmacy_id = :pharmB
                             AND po.status NOT IN ('cancelled','refunded')
                             AND o.payment_status NOT IN ('successful','refunded')";

        // ---- wallet ledger ----------------------------------------------------
        $ledgerSql = 'SELECT wt.* FROM wallet_transactions wt WHERE wt.pharmacy_id = :pharmC
                      ORDER BY wt.created_at DESC';

        $sql = match ($tab) {
            'outstanding' => $outstandingSql,
            'wallet'      => $ledgerSql,
            default       => $receivedSql,
        };

        $params = match ($tab) {
            'outstanding' => ['pharmB' => $pharmacyId],
            'wallet'      => ['pharmC' => $pharmacyId],
            default       => $receivedParams,
        };

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value('SELECT COUNT(*) FROM (' . $sql . ') AS t', $params),
            static function (Database $d, int $limit, int $offset) use ($sql, $params, $perPage, $page): array {
                $offset = ($page - 1) * $perPage;
                return $d->all($sql . " LIMIT $limit OFFSET $offset", $params);
            },
            $perPage,
            $page
        );

        $wallet = $db->first(
            'SELECT * FROM pharmacy_wallets WHERE pharmacy_id = ?',
            ['pharmacy_id' => $pharmacyId]
        ) ?? ['balance' => 0, 'pending_balance' => 0, 'total_earned' => 0, 'total_paid' => 0];

        $summary = [
            'received_total' => (float) $db->value(
                "SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay
                 JOIN orders o           ON o.id = pay.order_id
                 JOIN pharmacy_orders po ON po.order_id = o.id AND po.pharmacy_id = :p1
                 WHERE pay.status = 'successful'",
                ['p1' => $pharmacyId]
            ),
            'outstanding_total' => (float) $db->value(
                "SELECT COALESCE(SUM(po.total), 0) FROM pharmacy_orders po
                 WHERE po.pharmacy_id = :p2 AND po.status NOT IN ('cancelled','refunded','delivered')",
                ['p2' => $pharmacyId]
            ),
            'earnings_total' => (float) $db->value(
                "SELECT COALESCE(SUM(pharmacy_earnings), 0) FROM pharmacy_orders po
                 WHERE po.pharmacy_id = :p3 AND po.status = 'delivered'",
                ['p3' => $pharmacyId]
            ),
            'refunded_total' => (float) $db->value(
                "SELECT COALESCE(SUM(r.amount), 0) FROM refunds r
                 JOIN orders o           ON o.id = r.order_id
                 JOIN pharmacy_orders po ON po.order_id = o.id AND po.pharmacy_id = :p4
                 WHERE r.status = 'completed'",
                ['p4' => $pharmacyId]
            ),
        ];

        $this->view('pharmacy/payments', [
            'title'     => 'Payments',
            'paginator' => $paginator,
            'tab'       => $tab,
            'search'    => $search,
            'wallet'    => $wallet,
            'summary'   => $summary,
        ], 'layouts/dashboard');
    }
}
