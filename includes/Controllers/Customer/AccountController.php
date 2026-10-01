<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Services\OrderService;
use App\Session;
use App\Upload;

/**
 * Customer account: overview, profile, password change.
 */
final class AccountController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /account
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $user = Auth::user();
        $id   = (int) $user['id'];
        $db   = Database::instance();

        $stats = $db->first(
            "SELECT COUNT(*) AS total_orders,
                    COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered,
                    COALESCE(SUM(CASE WHEN status NOT IN ('delivered','cancelled') THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN payment_status = 'successful' THEN total ELSE 0 END), 0) AS spent
             FROM orders WHERE customer_id = ?",
            ['customer_id' => $id]
        ) ?? ['total_orders' => 0, 'delivered' => 0, 'active' => 0, 'spent' => 0];

        $recentOrders = $db->all(
            'SELECT o.id, o.order_number, o.status, o.payment_status, o.total, o.fulfilment_method, o.created_at,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    (SELECT GROUP_CONCAT(DISTINCT ph.name SEPARATOR ", ")
                       FROM order_items oi JOIN pharmacies ph ON ph.id = oi.pharmacy_id
                      WHERE oi.order_id = o.id) AS pharmacy_names
             FROM orders o WHERE o.customer_id = ?
             ORDER BY o.id DESC LIMIT 5',
            ['customer_id' => $id]
        );

        $recentProducts = $db->all(
            'SELECT p.id, p.slug, p.name, p.price, p.discount_price, p.stock_qty, ph.name AS pharmacy_name,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM wishlist_items wi
             JOIN wishlists w ON w.id = wi.wishlist_id
             JOIN products p ON p.id = wi.product_id
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE w.user_id = ? AND p.deleted_at IS NULL
             ORDER BY wi.id DESC LIMIT 4',
            ['user_id' => $id]
        );

        $this->view('customer/account', [
            'title'      => 'My account',
            'heading'    => 'Hello, ' . strtok((string) $user['full_name'], ' '),
            'user'       => $user,
            'stats'      => $stats,
            'recentOrders' => $recentOrders,
            'wishlist'   => $recentProducts,
            'cartCount'  => (new CartService())->rawCount(),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /account/profile
    // -----------------------------------------------------------------------
    public function profile(Request $request): void
    {
        $user = Auth::user();

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'full_name' => 'required|string|min:2|max:120',
                    'email'     => 'required|email|max:190',
                    'phone'     => 'required|phone|max:32',
                ],
                $request->all(),
                'customer/profile',
                '/account/profile'
            );

            $db   = Database::instance();
            $clash = $db->value(
                'SELECT id FROM users WHERE email = ? AND id <> ? AND deleted_at IS NULL LIMIT 1',
                ['email' => strtolower((string) $data['email']), 'id' => $user['id']]
            );
            if ($clash !== null) {
                Session::error('That email address is already in use by another account.');
                Response::redirect('/account/profile');
            }

            $update = [
                'full_name' => $data['full_name'],
                'email'     => strtolower((string) $data['email']),
                'phone'     => $data['phone'],
            ];

            // Changing the email invalidates the previous verification.
            if (strtolower((string) $data['email']) !== strtolower((string) $user['email'])) {
                $update['email_verified_at']  = null;
                $update['verification_token'] = bin2hex(random_bytes(20));
            }

            if ($request->file('profile_image') !== null) {
                $saved = Upload::store($request->file('profile_image'), 'profile');
                if ($saved !== null) {
                    $update['profile_image'] = $saved['path'];
                }
            }

            $db->update('users', $update, 'id = ?', ['id' => $user['id']]);

            (new \App\Services\AuditService())->log('customer.profile_updated', 'user', (int) $user['id'], 'Profile details updated');

            Auth::resetCache();
            Session::success('Your profile has been updated.');
            Response::redirect('/account/profile');
        }

        $this->view('customer/profile', [
            'title'   => 'My profile',
            'heading' => 'My profile',
            'user'    => $user,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /account/change-password
    // -----------------------------------------------------------------------
    public function changePassword(Request $request): void
    {
        $user = Auth::user();

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'current_password' => 'required|string',
                    'password'         => 'required|password|confirmed',
                ],
                $request->all(),
                'customer/change-password',
                '/account/change-password'
            );

            $record = Database::instance()->first(
                'SELECT password_hash FROM users WHERE id = ?',
                ['id' => $user['id']]
            );

            if (!password_verify((string) $data['current_password'], (string) ($record['password_hash'] ?? ''))) {
                Session::error('Your current password is not correct.');
                Response::redirect('/account/change-password');
            }

            Database::instance()->update('users', [
                'password_hash' => Auth::hashPassword((string) $data['password']),
                'remember_token' => null,
            ], 'id = ?', ['id' => $user['id']]);

            (new \App\Services\AuditService())->log('auth.password_changed', 'user', (int) $user['id'], 'Password changed by account owner');

            Session::success('Your password has been changed.');
            Response::redirect('/account');
        }

        $this->view('customer/change-password', [
            'title'   => 'Change password',
            'heading' => 'Change password',
        ], 'layouts/dashboard');
    }
}
