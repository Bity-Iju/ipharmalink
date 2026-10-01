<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\MailService;
use App\Session;

/**
 * Password recovery: request a reset link, then set a new password.
 *
 * The request step never reveals whether an address exists — the same
 * confirmation page is shown either way, so the form cannot be used to
 * enumerate customers.
 */
final class ForgotPasswordController extends Controller
{
    // -----------------------------------------------------------------------
    //  /forgot-password
    // -----------------------------------------------------------------------
    public function handle(Request $request): void
    {
        if (!$request->isPost()) {
            $this->view('auth/forgot-password', [
                'title'   => 'Reset your password',
                'heading' => 'Reset your password',
            ]);
            return;
        }

        $data = $this->validate(
            ['email' => 'required|email|max:190'],
            $request->all(),
            'auth/forgot-password',
            '/forgot-password'
        );

        $db    = Database::instance();
        $email = strtolower((string) $data['email']);
        $user  = $db->first(
            'SELECT id, full_name, email, status FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );

        if ($user !== null && $user['status'] === 'active') {
            $token = bin2hex(random_bytes(32));

            $db->update('users', [
                'reset_token'       => $token,
                'reset_expires_at'  => date('Y-m-d H:i:s', time() + 3600),   // one hour
            ], 'id = ?', ['id' => $user['id']]);

            (new MailService())->sendPasswordReset(
                (string) $user['email'],
                (string) $user['full_name'],
                $token
            );
        }

        // Identical response whether or not the account exists.
        Session::success('If that email address is registered, we have sent a password reset link. Please check your inbox and spam folder.');
        Response::redirect('/forgot-password');
    }

    // -----------------------------------------------------------------------
    //  /reset-password
    // -----------------------------------------------------------------------
    public function reset(Request $request): void
    {
        $token = (string) $request->input('token', '');

        if (!$request->isPost()) {
            $this->view('auth/reset-password', [
                'title'   => 'Choose a new password',
                'heading' => 'Choose a new password',
                'token'   => $token,
            ]);
            return;
        }

        $data = $this->validate(
            [
                'token'    => 'required|string|min:40|max:100',
                'password' => 'required|password|confirmed',
            ],
            $request->all(),
            'auth/reset-password',
            '/reset-password?token=' . urlencode($token)
        );

        $db    = Database::instance();
        $user  = $db->first(
            'SELECT id, full_name FROM users
             WHERE reset_token = ? AND reset_expires_at > NOW() AND deleted_at IS NULL LIMIT 1',
            ['token' => (string) $data['token']]
        );

        if ($user === null) {
            Session::error('That reset link is invalid or has expired. Please request a new one.');
            Response::redirect('/forgot-password');
        }

        $db->update('users', [
            'password_hash'     => Auth::hashPassword((string) $data['password']),
            'reset_token'       => null,
            'reset_expires_at'  => null,
            'remember_token'    => null,
            'failed_logins'     => 0,
            'locked_until'      => null,
        ], 'id = ?', ['id' => $user['id']]);

        (new \App\Services\AuditService())->log('auth.password_reset', 'user', (int) $user['id'], 'Password reset completed');

        Session::success('Your password has been updated. You can now sign in.');
        Response::redirect('/login');
    }
}
