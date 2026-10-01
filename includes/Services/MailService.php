<?php

/**
 * iPharmaLink :: Mail service
 * ---------------------------------------------------------------------------
 * Transport-agnostic. `log` driver (default) writes the message to
 * storage/logs/mail.log — ideal for local development and staging — while
 * `mail` hands off to PHP's mail(). SMTP keys in .env switch it on.
 */

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Logger;

final class MailService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = Config::str('mail.driver', 'log');
    }

    public function send(string $to, string $subject, string $body, string $recipientName = ''): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('Refusing to send mail to an invalid address', ['to' => $to]);
            return false;
        }

        $html = $this->wrap($subject, $body, $recipientName);

        return match ($this->driver) {
            'log'  => $this->writeToLog($to, $subject, $body),
            'mail' => $this->sendViaMail($to, $subject, $html),
            default => $this->writeToLog($to, $subject, $body),
        };
    }

    /** Send a templated message from templates/emails/*.php */
    public function sendTemplate(string $to, string $template, array $data, string $recipientName = ''): bool
    {
        $body = \App\View::capture('emails/' . $template, $data);
        $subject = $data['subject'] ?? 'iPharmaLink';
        return $this->send($to, (string) $subject, $body, $recipientName);
    }

    // -----------------------------------------------------------------------
    //  Transactional messages
    // -----------------------------------------------------------------------

    public function sendVerification(string $to, string $name, string $token): bool
    {
        $link = Config::str('app.url') . '/verify-email?token=' . urlencode($token);
        return $this->send($to, 'Verify your iPharmaLink email address', $this->paragraphs([
            "Hello {$name},",
            'Please confirm your email address to activate your iPharmaLink account.',
            '<a href="' . e($link) . '" style="background:#0A2A5E;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block;margin:12px 0">Verify Email Address</a>',
            'If you did not create this account you can safely ignore this email.',
        ]), $name);
    }

    public function sendPasswordReset(string $to, string $name, string $token): bool
    {
        $link = Config::str('app.url') . '/reset-password?token=' . urlencode($token);
        return $this->send($to, 'Reset your iPharmaLink password', $this->paragraphs([
            "Hello {$name},",
            'We received a request to reset your password. This link expires in 60 minutes.',
            '<a href="' . e($link) . '" style="background:#0A2A5E;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block;margin:12px 0">Reset Password</a>',
            'If you did not request this, your password is unchanged and no action is needed.',
        ]), $name);
    }

    public function sendPharmacySubmitted(string $to, string $pharmacyName): bool
    {
        return $this->send($to, 'Pharmacy registration received', $this->paragraphs([
            "Hello {$pharmacyName},",
            'Thank you for registering on iPharmaLink. Our compliance team will review your licence documents and pharmacy details.',
            'You will be notified by email once your storefront is approved. Verification usually takes 1–2 business days.',
        ]), $pharmacyName);
    }

    public function sendOrderConfirmation(string $to, string $name, string $orderNumber, float $total): bool
    {
        return $this->send($to, "Order {$orderNumber} confirmed", $this->paragraphs([
            "Hello {$name},",
            "Thank you for your order. We have received order <strong>{$orderNumber}</strong> for <strong>₦" . number_format($total, 2) . '</strong>.',
            'Each pharmacy will prepare their portion separately and you will receive updates as it progresses.',
            '<a href="' . e(Config::str('app.url')) . '/account/orders" style="background:#0A2A5E;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block;margin:12px 0">Track Your Order</a>',
        ]), $name);
    }

    // -----------------------------------------------------------------------

    /** @param list<string> $lines */
    private function paragraphs(array $lines): string
    {
        $html = '';
        foreach ($lines as $line) {
            $html .= '<p style="margin:0 0 14px;line-height:1.65">' . $line . '</p>';
        }
        return $html;
    }

    private function wrap(string $subject, string $body, string $name): string
    {
        $platform = \App\Setting::getString('general.platform_name', 'iPharmaLink');
        $support  = \App\Setting::getString('general.support_email', Config::str('mail.from_email'));
        $greeting = $name !== '' ? 'Hello ' . e($name) . ',' : 'Hello,';

        return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;background:#F2F6FB;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#12233D">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(10,42,94,.08)">
        <tr>
          <td style="background:#0A2A5E;padding:22px 28px">
            <span style="color:#fff;font-size:20px;font-weight:700;letter-spacing:.3px">+ {$platform}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:28px">
            <h1 style="margin:0 0 6px;font-size:19px;color:#0A2A5E">{$greeting}</h1>
            <h2 style="margin:0 0 18px;font-size:16px;color:#12233D;font-weight:600">{$subject}</h2>
            {$body}
          </td>
        </tr>
        <tr>
          <td style="background:#F2F6FB;padding:18px 28px;font-size:12px;color:#5B6B84">
            Need help? Contact us at <a href="mailto:{$support}" style="color:#3E6E9E">{$support}</a>.<br>
            You are receiving this message because you have an account on {$platform}.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }

    private function writeToLog(string $to, string $subject, string $body): bool
    {
        $dir = APP_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "\n===== %s =====\nTO: %s\nSUBJECT: %s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            strip_tags($body)
        );
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        return true;
    }

    private function sendViaMail(string $to, string $subject, string $html): bool
    {
        $from     = Config::str('mail.from_email');
        $fromName = Config::str('mail.from_name');
        $headers  = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: iPharmaLink',
        ];
        $sent = @mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$sent) {
            Logger::error('mail() failed', ['to' => $to, 'subject' => $subject]);
        }
        return (bool) $sent;
    }
}
