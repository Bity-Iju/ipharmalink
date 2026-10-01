<?php

declare(strict_types=1);

namespace App\Controllers\Supplier;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\View;

final class SupplierDashboardController extends Controller
{
    public function index(Request $request): void
    {
        $supplierId = Auth::supplierId();
        $supplier = Database::instance()->first(
            'SELECT s.*, u.full_name AS owner_name
             FROM suppliers s JOIN users u ON u.id = s.owner_id
             WHERE s.id = ? AND s.owner_id = ? AND s.status = \'approved\' AND s.deleted_at IS NULL',
            [$supplierId, Auth::id()]
        );
        if ($supplier === null) {
            throw HttpException::notFound('Supplier workspace not found.');
        }

        $documents = Database::instance()->all(
            'SELECT doc_type, review_status, uploaded_at FROM supplier_documents
             WHERE supplier_id = ? ORDER BY id ASC',
            [$supplierId]
        );

        $this->view('supplier/dashboard', [
            'title' => 'Supplier workspace',
            'heading' => 'Supplier workspace',
            'sidebar' => View::capture('supplier/partials/sidebar'),
            'supplier' => $supplier,
            'documents' => $documents,
        ], 'layouts/dashboard');
    }
}