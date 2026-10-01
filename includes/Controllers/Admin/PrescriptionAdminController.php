<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Paginator;
use App\Request;

/**
 * Platform prescription oversight — /admin/prescriptions
 *
 * A prescription is a compliance artefact, so this list is audit-oriented:
 * who submitted it, for which order, who reviewed it and when.
 */
final class PrescriptionAdminController extends Controller
{
    private const PER_PAGE = 25;

    /** GET /admin/prescriptions */
    public function index(Request $request): void
    {
        $db      = Database::instance();
        $status  = $request->trimmed('status');
        $search  = $request->trimmed('q');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]         = 'pr.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(pr.original_name LIKE :q OR u.full_name LIKE :q2 OR ph.name LIKE :q3)';
            $params += ['q' => '%' . $search . '%', 'q2' => '%' . $search . '%', 'q3' => '%' . $search . '%'];
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $from = 'FROM prescriptions pr
                 JOIN users u          ON u.id = pr.user_id
                 JOIN pharmacies ph    ON ph.id = pr.pharmacy_id
                 LEFT JOIN orders o    ON o.id = pr.order_id
                 LEFT JOIN users rev   ON rev.id = pr.reviewed_by';

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) $from WHERE $whereSql", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $from, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT pr.*, u.full_name AS customer_name, ph.name AS pharmacy_name,
                            o.order_number, rev.full_name AS reviewer_name
                     $from
                     WHERE $whereSql
                     ORDER BY pr.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        $counts = [];
        foreach (['pending', 'approved', 'rejected', 'expired'] as $state) {
            $counts[$state] = (int) $db->value('SELECT COUNT(*) FROM prescriptions WHERE status = ?', ['s' => $state]);
        }
        $counts['all'] = (int) $db->value('SELECT COUNT(*) FROM prescriptions');

        $this->view('admin/prescriptions/index', [
            'title'     => 'Prescriptions',
            'paginator' => $paginator,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
        ], 'layouts/dashboard');
    }
}
