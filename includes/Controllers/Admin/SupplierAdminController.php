<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controller;
use App\Config;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Session;
use App\View;

final class SupplierAdminController extends Controller
{
    private const STATUSES = ['pending', 'under_review', 'approved', 'rejected', 'suspended', 'deactivated'];

    public function index(Request $request): void
    {
        $this->listing($request);
    }

    public function pending(Request $request): void
    {
        $this->listing($request, 'pending');
    }

    public function show(Request $request, array $params): void
    {
        $supplier = Database::instance()->first(
            'SELECT s.*, u.full_name AS owner_name, u.email AS owner_email
             FROM suppliers s JOIN users u ON u.id = s.owner_id
             WHERE s.id = ? AND s.deleted_at IS NULL LIMIT 1',
            [(int) $this->param('id', $params)]
        );
        if ($supplier === null) {
            throw HttpException::notFound('Supplier not found.');
        }

        $documents = Database::instance()->all(
            'SELECT * FROM supplier_documents WHERE supplier_id = ? ORDER BY id ASC',
            [(int) $supplier['id']]
        );

        $this->view('admin/suppliers/show', [
            'title' => (string) $supplier['name'],
            'heading' => (string) $supplier['name'],
            'sidebar' => View::capture('admin/partials/sidebar'),
            'supplier' => $supplier,
            'documents' => $documents,
            'statuses' => self::STATUSES,
            'breadcrumbs' => [['label' => 'Suppliers', 'url' => '/admin/suppliers'], ['label' => (string) $supplier['name']]],
        ], 'layouts/dashboard');
    }

    public function document(Request $request, array $params): void
    {
        $supplierId = (int) $this->param('id', $params);
        $documentId = (int) $this->param('documentId', $params);
        $document = Database::instance()->first(
            'SELECT file_path, original_name FROM supplier_documents WHERE id = ? AND supplier_id = ?',
            [$documentId, $supplierId]
        );
        if ($document === null) {
            throw HttpException::notFound('Verification document not found.');
        }

        $uploadRoot = realpath(Config::str('uploads.path'));
        $filePath = realpath(APP_ROOT . '/' . ltrim((string) $document['file_path'], '/'));
        if ($uploadRoot === false || $filePath === false || !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            throw HttpException::notFound('Verification document not found.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw HttpException::notFound('Verification document not found.');
        }

        $filename = preg_replace('/[\r\n"]/', '', basename((string) ($document['original_name'] ?: $filePath)));
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id = (int) $this->param('id', $params);
        $db = Database::instance();
        $supplier = $db->first('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($supplier === null) {
            throw HttpException::notFound('Supplier not found.');
        }

        $status = (string) $request->input('status', '');
        $reason = trim((string) $request->input('reason', ''));
        if (!in_array($status, self::STATUSES, true)) {
            Session::error('That supplier status is not valid.');
            Response::redirect('/admin/suppliers/' . $id);
        }
        if (in_array($status, ['rejected', 'suspended', 'deactivated'], true) && $reason === '') {
            Session::error('Please provide a reason for this decision.');
            Response::redirect('/admin/suppliers/' . $id);
        }

        $db->transaction(function () use ($db, $id, $status, $reason): void {
            $db->update('suppliers', [
                'status' => $status,
                'status_reason' => $reason !== '' ? $reason : null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);

            if ($status === 'approved') {
                $db->run('UPDATE supplier_documents SET review_status = \'approved\' WHERE supplier_id = ?', [$id]);
            } elseif ($status === 'rejected') {
                $db->run(
                    'UPDATE supplier_documents SET review_status = \'rejected\', review_note = ? WHERE supplier_id = ?',
                    [$reason, $id]
                );
            }
        });

        (new AuditService())->log('supplier.' . $status, 'supplier', $id, sprintf(
            'Supplier "%s" %s%s',
            (string) $supplier['name'],
            $status,
            $reason !== '' ? ': ' . $reason : ''
        ));

        $titles = [
            'approved' => 'Supplier registration approved',
            'under_review' => 'Supplier registration under review',
            'rejected' => 'Supplier registration not approved',
            'suspended' => 'Supplier account suspended',
            'deactivated' => 'Supplier account deactivated',
            'pending' => 'Supplier registration returned to pending review',
        ];
        (new NotificationService())
            ->to((int) $supplier['owner_id'], 'supplier.' . $status, $titles[$status], $reason ?: null, '/supplier/dashboard', 'supplier', $id)
            ->send(false);

        Session::success(sprintf('"%s" is now %s.', (string) $supplier['name'], str_replace('_', ' ', $status)));
        Response::redirect('/admin/suppliers/' . $id);
    }

    private function listing(Request $request, string $forcedStatus = ''): void
    {
        $status = $forcedStatus !== '' ? $forcedStatus : (string) $request->query('status', '');
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $search = trim((string) $request->query('q', ''));
        $where = ['s.deleted_at IS NULL'];
        $params = [];
        if ($status !== '') {
            $where[] = 's.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(s.name LIKE :name OR s.registered_name LIKE :registered OR s.registration_number LIKE :registration OR s.email LIKE :email)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $params += ['name' => $like, 'registered' => $like, 'registration' => $like, 'email' => $like];
        }

        $db = Database::instance();
        $suppliers = $db->all(
            'SELECT s.id, s.name, s.registered_name, s.registration_number, s.email, s.city, s.state,
                    s.status, s.created_at, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM supplier_documents d WHERE d.supplier_id = s.id) AS document_count
             FROM suppliers s JOIN users u ON u.id = s.owner_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(s.status, \'pending\', \'under_review\', \'approved\', \'rejected\', \'suspended\', \'deactivated\'), s.created_at DESC
             LIMIT 300',
            $params
        );
        $counts = array_fill_keys(array_merge([''], self::STATUSES), 0);
        foreach ($db->all('SELECT status, COUNT(*) AS total FROM suppliers WHERE deleted_at IS NULL GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
            $counts[''] += (int) $row['total'];
        }

        $this->view('admin/suppliers/index', [
            'title' => 'Wholesale suppliers',
            'heading' => 'Wholesale suppliers',
            'sidebar' => View::capture('admin/partials/sidebar'),
            'suppliers' => $suppliers,
            'status' => $status,
            'search' => $search,
            'statuses' => self::STATUSES,
            'counts' => $counts,
        ], 'layouts/dashboard');
    }
}