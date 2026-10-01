<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Session;
use App\Setting;

/**
 * Platform configuration.
 *
 * Secrets are stored in platform_settings flagged is_secret and are never
 * echoed back to the browser — the forms render a masked placeholder and only
 * write a value when the admin actually types a new one.
 */
final class SettingAdminController extends Controller
{
    /** Placeholder shown in place of a stored secret. */
    private const SECRET_MASK = '••••••••••••';

    // -----------------------------------------------------------------------
    //  General
    // -----------------------------------------------------------------------

    /** GET|POST /admin/settings */
    public function general(Request $request): void
    {
        if ($request->isPost()) {
            $this->save($request, [
                'general.site_name'          => $request->trimmed('site_name'),
                'general.tagline'            => $request->trimmed('tagline'),
                'general.meta_description'   => $request->trimmed('meta_description'),
                'general.support_email'      => $request->trimmed('support_email'),
                'general.support_phone'      => $request->trimmed('support_phone'),
                'general.address'            => $request->trimmed('address'),
                'general.currency'           => $request->trimmed('currency', 'NGN'),
                'general.currency_symbol'    => $request->trimmed('currency_symbol', '₦'),
                'system.pagination_per_page' => (string) max(6, min(100, $request->int('pagination_per_page', 24))),
                'system.default_tax_rate'    => (string) max(0, $request->float('default_tax_rate')),
                'general.enable_reviews'     => $request->bool('enable_reviews') ? '1' : '0',
                'general.maintenance_mode'   => $request->bool('maintenance_mode') ? '1' : '0',
            ], 'admin.settings.general');
        }

        $this->render($request, 'admin.settings.general', 'General settings', [
            'maintenance' => Setting::getBool('general.maintenance_mode'),
        ]);
    }

    // -----------------------------------------------------------------------
    //  Payments
    // -----------------------------------------------------------------------

    /** GET|POST /admin/payment-settings */
    public function payment(Request $request): void
    {
        if ($request->isPost()) {
            $this->save($request, [
                'payment.default_gateway'     => $request->trimmed('default_gateway', 'manual'),
                'payment.allow_bank_transfer' => $request->bool('allow_bank_transfer') ? '1' : '0',
                'payment.allow_wallet'        => $request->bool('allow_wallet') ? '1' : '0',
                'payment.bank_name'           => $request->trimmed('bank_name'),
                'payment.account_number'      => $request->trimmed('account_number'),
                'payment.account_name'        => $request->trimmed('account_name'),
                'payment.minimum_order'       => (string) max(0, $request->float('minimum_order')),
                'payment.require_proof'       => $request->bool('require_proof') ? '1' : '0',
            ], 'admin.settings.payment');
        }

        $gateways = Database::instance()->all(
            'SELECT code, name, is_enabled, is_sandbox, credentials FROM payment_gateways ORDER BY sort_order ASC, id ASC'
        );

        // Never leak stored secret keys to the settings screen.
        foreach ($gateways as &$gateway) {
            $credentials = json_decode((string) ($gateway['credentials'] ?? '{}'), true);
            $gateway['credentials'] = is_array($credentials) ? array_keys($credentials) : [];
            $gateway['masked'] = array_fill_keys($gateway['credentials'], true);
        }
        unset($gateway);

        $this->render($request, 'admin.settings.payment', 'Payment settings', [
            'gateways'   => $gateways,
            'mask'       => self::SECRET_MASK,
        ]);
    }

    /** POST /admin/gateways/{code} */
    public function toggleGateway(Request $request, array $params): void
    {
        $code   = $this->param('code', $params);
        $enable = $request->bool('is_enabled');

        $gateway = Database::instance()->first('SELECT * FROM payment_gateways WHERE code = ?', ['code' => $code]);
        if ($gateway === null) {
            throw new HttpException(404, 'Payment gateway not found.');
        }

        $credentials = json_decode((string) ($gateway['credentials'] ?? '{}'), true);
        $credentials = is_array($credentials) ? $credentials : [];

        // Only overwrite keys the admin actually submitted a value for.
        foreach (['secret_key', 'public_key', 'webhook_secret', 'merchant_id', 'api_key'] as $key) {
            $submitted = $request->trimmed($key);
            if ($submitted !== '' && $submitted !== self::SECRET_MASK) {
                $credentials[$key] = $submitted;
            }
        }

        Database::instance()->update(
            'payment_gateways',
            [
                'is_enabled'   => $enable ? 1 : 0,
                'is_sandbox'   => $request->bool('is_sandbox') ? 1 : 0,
                'credentials'  => json_encode($credentials),
            ],
            'code = :code',
            ['code' => $code]
        );

        (new AuditService($request))->logChange(
            'admin.gateway.updated',
            'payment_gateway',
            (int) $gateway['id'],
            ['is_enabled' => (int) $gateway['is_enabled'], 'is_sandbox' => (int) $gateway['is_sandbox']],
            ['is_enabled' => $enable ? 1 : 0]
        );

        Session::success(strtoupper($code) . ' is now ' . ($enable ? 'enabled' : 'disabled') . '.');
        Response::back('/admin/payment-settings');
    }

    // -----------------------------------------------------------------------
    //  Delivery
    // -----------------------------------------------------------------------

    /** GET|POST /admin/delivery-settings */
    public function delivery(Request $request): void
    {
        if ($request->isPost()) {
            $this->save($request, [
                'delivery.flat_fee'         => (string) max(0, $request->float('flat_fee')),
                'delivery.free_over'        => (string) max(0, $request->float('free_over')),
                'delivery.per_km_fee'       => (string) max(0, $request->float('per_km_fee')),
                'delivery.default_eta_days' => (string) max(0, $request->int('default_eta_days', 1)),
                'delivery.pickup_enabled'   => $request->bool('pickup_enabled') ? '1' : '0',
                'delivery.otp_required'     => $request->bool('otp_required') ? '1' : '0',
                'delivery.proof_required'   => $request->bool('proof_required') ? '1' : '0',
            ], 'admin.settings.delivery');
        }

        $this->render($request, 'admin.settings.delivery', 'Delivery settings');
    }

    // -----------------------------------------------------------------------
    //  Email
    // -----------------------------------------------------------------------

    /** GET|POST /admin/email-settings */
    public function email(Request $request): void
    {
        if ($request->isPost()) {
            $this->save($request, [
                'mail.driver'      => $request->trimmed('driver', 'log'),
                'mail.from_name'   => $request->trimmed('from_name', 'iPharmaLink'),
                'mail.from_email'  => $request->trimmed('from_email'),
                'smtp.host'        => $request->trimmed('smtp_host'),
                'smtp.port'        => (string) max(1, min(65535, $request->int('smtp_port', 587))),
                'smtp.username'    => $request->trimmed('smtp_username'),
                'smtp.encryption'  => $request->trimmed('smtp_encryption', 'tls'),
            ], 'admin.settings.email');

            // The SMTP password is write-only.
            $password = $request->trimmed('smtp_password');
            if ($password !== '' && $password !== self::SECRET_MASK) {
                $this->write('smtp.password', $password, true);
            }
        }

        $this->render($request, 'admin.settings.email', 'Email settings', [
            'hasSmtpPassword' => Setting::getString('smtp.password') !== '',
            'mask'            => self::SECRET_MASK,
        ]);
    }

    // -----------------------------------------------------------------------
    //  SMS
    // -----------------------------------------------------------------------

    /** POST /admin/sms-settings */
    public function sms(Request $request): void
    {
        $this->save($request, [
            'sms.driver'     => $request->trimmed('driver', 'log'),
            'sms.sender_id'  => $request->trimmed('sender_id', 'iPharmaLink'),
            'sms.enabled'    => $request->bool('enabled') ? '1' : '0',
        ], 'admin.settings.sms');
    }

    // -----------------------------------------------------------------------
    //  Security
    // -----------------------------------------------------------------------

    /** GET|POST /admin/security-settings */
    public function security(Request $request): void
    {
        if ($request->isPost()) {
            $this->save($request, [
                'security.max_login_attempts' => (string) max(3, min(20, $request->int('max_login_attempts', 5))),
                'security.lockout_minutes'    => (string) max(1, min(1440, $request->int('lockout_minutes', 15))),
                'security.session_lifetime'   => (string) max(300, min(86400, $request->int('session_lifetime', 3600))),
                'security.force_https'        => $request->bool('force_https') ? '1' : '0',
                'security.require_prescription_upload' => $request->bool('require_prescription_upload') ? '1' : '0',
                'security.password_min_length' => (string) max(6, min(64, $request->int('password_min_length', 8))),
            ], 'admin.settings.security');
        }

        $this->render($request, 'admin.settings.security', 'Security settings');
    }

    // -----------------------------------------------------------------------
    //  Internals
    // -----------------------------------------------------------------------

    /**
     * Persist a batch of settings and redirect back.
     *
     * @param array<string,string> $values
     */
    private function save(Request $request, array $values, string $returnTo): void
    {
        Setting::setMany($values);

        (new AuditService($request))->log(
            'admin.settings.updated',
            'setting',
            null,
            'Updated: ' . implode(', ', array_keys($values))
        );

        Session::success('Settings saved.');
        Response::back($returnTo);
    }

    /**
     * Write a single setting, flagging it as a secret.
     */
    private function write(string $key, string $value, bool $secret = false): void
    {
        $db = Database::instance();

        $exists = $db->value('SELECT id FROM platform_settings WHERE key_name = ?', ['key_name' => $key]);
        if ($exists !== null) {
            $db->update('platform_settings', ['value' => $value, 'is_secret' => $secret ? 1 : 0], 'key_name = :k', ['k' => $key]);
        } else {
            $db->insert('platform_settings', [
                'group_name' => explode('.', $key)[0],
                'key_name'   => $key,
                'value'      => $value,
                'is_secret'  => $secret ? 1 : 0,
            ]);
        }

        Setting::flush();
    }

    /**
     * Render a settings page, pulling every `*.key` value it declares.
     *
     * @param array<string,mixed> $extra
     */
    private function render(Request $request, string $template, string $heading, array $extra = []): void
    {
        $db  = Database::instance();
        $all = [];

        // Values keyed by the short name each form field uses.
        $rows = $db->all('SELECT key_name, value, is_secret FROM platform_settings');
        foreach ($rows as $row) {
            $short = str_contains((string) $row['key_name'], '.')
                ? substr((string) $row['key_name'], strpos((string) $row['key_name'], '.') + 1)
                : (string) $row['key_name'];

            // Secrets are never hydrated into a form field.
            $all[$short] = (int) $row['is_secret'] === 1 ? self::SECRET_MASK : (string) $row['value'];
        }

        $this->view($template, $extra + [
            'title'  => $heading,
            'values' => $all,
            'errors' => [],
        ], 'layouts/dashboard');
    }
}
