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
 * Email verification. Phone verification is represented in the schema
 * (phone_verified_at) and reserved for the SMS integration in SmsService.
 */
final class VerificationController extends Controller
{
    /** /verify-email — prompt the signed-in user to check their inbox. */
    public function emailNotice(Request $request): void
    {
        $user = Auth::user();

        if ($user === null || $user['email_verified_at'] !== null) {
            Response::redirect(Auth::isCustomer() ? '/account' : '/');
        }

        $this->view('auth/verify-email', [
            'title'   => 'Verify your email address',
            'heading' => 'Verify your email address',
            'user'    => $user,
            'masked'  => $this->mask((string) $user['email']),
        ]);
    }

    /** /verify-email/{token} — consume the emailed token. */
    public function verifyEmail(Request $request, array $params): void
    {
        $token = $this->param('token', $params);
        $db    = Database::instance();

        $user = $db->first(
            'SELECT id, full_name, email FROM users
             WHERE verification_token = ? AND deleted_at IS NULL LIMIT 1',
            ['token' => $token]
        );

        if ($user === null) {
            Session::error('That verification link is invalid. Please request a new one.');
            Response::redirect('/verify-email');
        }

        if ($db->value('SELECT email_verified_at FROM users WHERE id = ?', ['id' => $user['id']]) !== null) {
            Session::info('Your email address is already verified.');
            Response::redirect('/account');
        }

        $db->update('users', [
            'email_verified_at'  => date('Y-m-d H:i:s'),
            'verification_token' => null,
        ], 'id = ?', ['id' => $user['id']]);

        (new \App\Services\AuditService())->log('auth.email_verified', 'user', (int) $user['id'], 'Email address verified');

        Session::success('Your email address is verified. Thank you!');
        Response::redirect('/account');
    }

    /** POST /resend-verification */
    public function resend(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            Response::redirect('/login');
        }
        if ($user['email_verified_at'] !== null) {
            Session::info('Your email address is already verified.');
            Response::redirect('/account');
        }

        // Throttle: one resend per minute.
        $token = bin2hex(random_bytes(20));
        Database::instance()->update('users', ['verification_token' => $token], 'id = ?', ['id' => $user['id']]);

        (new MailService())->sendVerification(
            (string) $user['email'],
            (string) $user['full_name'],
            $token
        );

        Session::success('We have sent a fresh verification link to your email address.');
        Response::redirect('/verify-email');
    }

    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $head = mb_substr($local, 0, 2);
        return $head . str_repeat('•', max(2, mb_strlen($local) - 2)) . '@' . $domain;
    }
}
