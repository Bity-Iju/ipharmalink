<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Paginator;
use App\Request;

/**
 * Platform delivery oversight — /admin/deliveries
 *
 * Read-only: assignment and completion belong to the pharmacy and the rider.
 * An admin sees the whole network to spot systemic delivery problems.
 */
final class DeliveryAdminController extends Controller
{
    private const PER_PAGE = 25;

    /** GET /admin/deliveries */
    public function index(Request $request): void
    {
        $db      = Database::instance();
        $status  = $request->trimmed('status');
        $search  = $request->trimmed('q');
        $from    = $request->trimmed('from');
        $to      = $request->trimmed('to');
        $perPage = $this->perPage(self::PER_PAGE);
        $page    = $this->page();

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]         = 'de.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(de.tracking_number LIKE :q OR ph.name LIKE :q2 OR rider.full_name LIKE :q3)';
            $params += ['q' => '%' . $search . '%', 'q2' => '%' . $search . '%', 'q3' => '%' . $search . '%'];
        }
        if ($from !== '' && strtotime($from) !== false) {
            $where[]        = 'de.created_at >= :from';
            $params['from'] = date('Y-m-d H:i:s', (int) strtotime($from . ' 00:00:00'));
        }
        if ($to !== '' && strtotime($to) !== false) {
            $where[]      = 'de.created_at <= :to';
            $params['to'] = date('Y-m-d H:i:s', (int) strtotime($to . ' 23:59:59'));
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $from = 'FROM deliveries de
                 JOIN pharmacies ph ON ph.id = de.pharmacy_id
                 LEFT JOIN delivery_personnel dp ON dp.id = de.personnel_id
                 LEFT JOIN users rider             ON rider.id = dp.user_id';

        $paginator = Paginator::build(
            static fn(Database $d): int => (int) $d->value("SELECT COUNT(*) $from WHERE $whereSql", $params),
            static function (Database $d, int $limit, int $offset) use ($perPage, $page, $from, $whereSql, $params): array {
                $offset = ($page - 1) * $perPage;
                return $d->all(
                    "SELECT de.*, ph.name AS pharmacy_name, rider.full_name AS rider_name
                     $from
                     WHERE $whereSql
                     ORDER BY de.created_at DESC
                     LIMIT $limit OFFSET $offset",
                    $params
                );
            },
            $perPage,
            $page
        );

        // Performance counters, independent of the active filter.
        $counts = [];
        foreach (['pending', 'assigned', 'picked_up', 'in_transit', 'delivered', 'failed'] as $state) {
            $counts[$state] = (int) $db->value('SELECT COUNT(*) FROM deliveries WHERE status = ?', ['s' => $state]);
        }
        $counts['active'] = (int) $db->value(
            "SELECT COUNT(*) FROM deliveries WHERE status NOT IN ('delivered','failed','cancelled')"
        );
        $counts['all']    = (int) $db->value('SELECT COUNT(*) FROM deliveries');

        $this->view('admin/deliveries/index', [
            'title'     => 'Deliveries',
            'paginator' => $paginator,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
        ], 'layouts/dashboard');
    }
}
