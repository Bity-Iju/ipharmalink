<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\MailService;
use App\Session;
use App\View;

/**
 * Pharmacy staff. Only the owner may manage staff (enforced by the
 * `pharmacy_owner` middleware on the routes).
 */
final class StaffController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /pharmacy/staff
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $staff = Database::instance()->all(
            'SELECT s.*, u.full_name, u.email, u.phone, u.status AS user_status, u.last_login_at
             FROM pharmacy_staff s JOIN users u ON u.id = s.user_id
             WHERE s.pharmacy_id = ?
             ORDER BY s.id ASC',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/staff', [
            'title'   => 'Staff',
            'heading' => 'Pharmacy staff',
            'sidebar' => View::capture('pharmacy/partials/sidebar'),
            'staff'   => $staff,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/staff
    // -----------------------------------------------------------------------
    public function store(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $data = $this->validate(
            [
                'full_name' => 'required|string|min:2|max:120',
                'email'     => 'required|email|max:190',
                'phone'     => 'nullable|phone|max:32',
                'job_title' => 'nullable|string|max:80',
                'can_manage_orders'    => 'nullable|bool',
                'can_manage_inventory' => 'nullable|bool',
                'can_manage_products'  => 'nullable|bool',
                'can_view_reports'     => 'nullable|bool',
            ],
            $request->all(),
            'pharmacy/staff',
            '/pharmacy/staff'
        );

        $db    = Database::instance();
        $email = strtolower((string) $data['email']);

        $existing = $db->first(
            'SELECT u.id FROM users u WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );

        if ($existing !== null) {
            // Attach an existing account rather than creating a duplicate.
            $already = $db->value(
                'SELECT id FROM pharmacy_staff WHERE pharmacy_id = ? AND user_id = ? LIMIT 1',
                ['pharmacy_id' => $pharmacyId, 'user_id' => $existing['id']]
            );
            if ($already !== null) {
                Session::error('That person is already on your staff list.');
                Response::redirect('/pharmacy/staff');
            }
            $userId = (int) $existing['id'];
        } else {
            $roleId = (int) $db->value("SELECT id FROM roles WHERE name = 'pharmacy_staff'");
            $userId = $db->insert('users', [
                'role_id'       => $roleId,
                'full_name'     => $data['full_name'],
                'email'         => $email,
                'phone'         => $data['phone'] ?? null,
                // A random unusable hash: the account is activated by an
                // admin-set password, never a guessed one.
                'password_hash' => Auth::hashPassword(bin2hex(random_bytes(24))),
                'status'        => 'active',
            ]);
            $db->insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId]);

            (new MailService())->sendStaffWelcome($email, (string) $data['full_name']);
        }

        $db->insert('pharmacy_staff', [
            'pharmacy_id'         => $pharmacyId,
            'user_id'             => $userId,
            'job_title'           => $data['job_title'] ?? null,
            'can_manage_orders'   => $request->bool('can_manage_orders') ? 1 : 0,
            'can_manage_inventory' => $request->bool('can_manage_inventory') ? 1 : 0,
            'can_manage_products' => $request->bool('can_manage_products') ? 1 : 0,
            'can_view_reports'    => $request->bool('can_view_reports') ? 1 : 0,
            'status'              => 'active',
        ]);

        (new AuditService())->log(
            'pharmacy.staff_added',
            'pharmacy',
            $pharmacyId,
            'Staff member added: ' . (string) $data['full_name']
        );

        Session::success('Staff member added.');
        Response::redirect('/pharmacy/staff');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/staff/{id}
    // -----------------------------------------------------------------------
    public function update(Request $request, array $params): void
    {
        $id        = (int) $this->param('id', $params);
        $pharmacyId = (int) Auth::pharmacyId();
        $db        = Database::instance();

        $staff = $this->ownedStaff($id, $pharmacyId);

        $db->update('pharmacy_staff', [
            'job_title'           => trim((string) $request->input('job_title', '')) ?: null,
            'can_manage_orders'   => $request->bool('can_manage_orders') ? 1 : 0,
            'can_manage_inventory' => $request->bool('can_manage_inventory') ? 1 : 0,
            'can_manage_products' => $request->bool('can_manage_products') ? 1 : 0,
            'can_view_reports'    => $request->bool('can_view_reports') ? 1 : 0,
            'status'              => $request->input('status', 'active') === 'suspended' ? 'suspended' : 'active',
        ], 'id = ?', ['id' => $id]);

        (new AuditService())->log('pharmacy.staff_updated', 'pharmacy_staff', $id, 'Staff permissions updated');

        Session::success('Staff record updated.');
        Response::redirect('/pharmacy/staff');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/staff/{id}/delete
    // -----------------------------------------------------------------------
    public function destroy(Request $request, array $params): void
    {
        $id         = (int) $this->param('id', $params);
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $staff = $this->ownedStaff($id, $pharmacyId);

        // Removing access to the pharmacy, not the person's account.
        $db->delete('pharmacy_staff', 'id = ?', ['id' => $id]);

        (new AuditService())->log('pharmacy.staff_removed', 'pharmacy_staff', $id, 'Staff member removed from pharmacy');

        Session::success('Staff member removed from your pharmacy.');
        Response::redirect('/pharmacy/staff');
    }

    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function ownedStaff(int $id, int $pharmacyId): array
    {
        $staff = Database::instance()->first(
            'SELECT s.*, u.full_name FROM pharmacy_staff s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.pharmacy_id = ? LIMIT 1',
            ['id' => $id, 'pharmacy_id' => $pharmacyId]
        );

        if ($staff === null) {
            throw HttpException::notFound('That staff member was not found in your pharmacy.');
        }
        return $staff;
    }
}
