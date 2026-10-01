<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Paginator;
use App\Request;
use App\Services\AuditService;

/**
 * Audit trail browser — /admin/audit-logs
 *
 * Read-only. The audit log is append-only from the application's point of
 * view; nothing on this screen can edit or delete an entry.
 */
final class AuditAdminController extends Controller
{
    private const PER_PAGE = 50;

    // -----------------------------------------------------------------------
    //  GET /admin/audit-logs
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $filters = [
            'q'           => $request->trimmed('q'),
            'action'      => $request->trimmed('action'),
            'entity_type' => $request->trimmed('entity_type'),
            'from'        => $request->trimmed('from'),
            'to'          => $request->trimmed('to'),
        ];

        $result   = (new AuditService())->search($filters, $this->page(), $this->perPage(self::PER_PAGE));
        $paginator = new Paginator($result['rows'], $result['total'], self::PER_PAGE, $this->page());

        // Facet values, so the filter dropdowns are not hand-typed.
        $db       = Database::instance();
        $entities = $db->column(
            "SELECT DISTINCT entity_type FROM audit_logs
             WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type"
        );
        $actions  = $db->column(
            "SELECT DISTINCT action FROM audit_logs ORDER BY action"
        );

        $this->view('admin/audit/index', [
            'title'       => 'Audit logs',
            'paginator'   => $paginator,
            'filters'     => $filters,
            'entityTypes' => $entities,
            'actions'     => $actions,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /admin/audit-logs/{id}
    // -----------------------------------------------------------------------
    public function show(Request $request, array $params): void
    {
        $entry = Database::instance()->first(
            'SELECT * FROM audit_logs WHERE id = ?',
            ['id' => (int) $this->param('id', $params)]
        );

        if ($entry === null) {
            throw new HttpException(404, 'Audit entry not found.');
        }

        $this->view('admin/audit/show', [
            'title' => 'Audit entry #' . $entry['id'],
            'entry' => $entry,
        ], 'layouts/dashboard');
    }
}
