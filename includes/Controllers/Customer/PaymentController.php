<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\Paginator;
use App\Request;

/**
 * Customer payment history — /account/payments
 *
 * Read-only, and scoped hard to the signed-in customer: every query is
 * filtered by their own user id, so there is no way to reach another
 * customer's money by editing the URL.
 */
final class PaymentController extends Controller
{
    private const PER_PAGE = 20;

    /** GET /account/payments */
    public function index(Request $request): void
    {
        $db         = Database::instance();
        $customerId = (int) Auth::id();
        $status     = $request->trimmed('status');
        $perPage    = $this->perPage(self::PER_PAGE);
        $page       = $this->page();

        $where  = ['pay.customer_id = :cust'];
        $params = ['cust' => $customerId];

        if ($status !== '') {
            $where[]           = 'pay.status = :status';
            $params['status']  = $status;
        }

        $whereSql = implode(' AND ', $where);

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value(
                "SELECT COUNT(*) FROM payments pay WHERE $whereSql",
                $params
            ),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT pay.id, pay.order_id, pay.amount, pay.status, pay.gateway_code,
                            pay.reference, pay.paid_at, pay.created_at,
                            o.order_number, o.fulfilment_method
                     FROM payments pay
                     JOIN orders o ON o.id = pay.order_id
                     WHERE $whereSql
                     ORDER BY pay.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $summary = [
            'total'     => (int) $db->value('SELECT COUNT(*) FROM payments WHERE customer_id = ?', ['c' => $customerId]),
            'paid'      => (float) $db->value(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ? AND status = 'successful'",
                ['c' => $customerId]
            ),
            'pending'   => (float) $db->value(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE customer_id = ? AND status IN ('pending','processing')",
                ['c' => $customerId]
            ),
            'refunded'  => (float) $db->value(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ? AND status = 'refunded'",
                ['c' => $customerId]
            ),
        ];

        $this->view('customer/payments', [
            'title'     => 'Payments',
            'paginator' => $paginator,
            'summary'   => $summary,
            'status'    => $status,
        ]);
    }
}
