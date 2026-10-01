<?php

/**
 * iPharmaLink :: Application configuration
 * ---------------------------------------------------------------------------
 * Static values live here; environment-driven values come from .env.
 * Everything is returned as a flat array and frozen into App\Config.
 */

declare(strict_types=1);

return [
    // ---- Application ------------------------------------------------------
    'app.name'          => App\Config::env('APP_NAME', 'iPharmaLink'),
    'app.env'           => App\Config::env('APP_ENV', 'production'),
    'app.debug'         => App\Config::env('APP_DEBUG', 'false') === 'true',
    'app.url'           => rtrim(App\Config::env('APP_URL', 'http://localhost:8000'), '/'),
    'app.timezone'      => App\Config::env('APP_TIMEZONE', 'Africa/Lagos'),
    'app.locale'        => 'en_NG',
    'app.currency'      => App\Config::env('APP_CURRENCY', 'NGN'),
    'app.key'           => App\Config::env('APP_KEY', ''),   // used for webhook HMAC + file encryption

    // ---- Database (MySQL / MariaDB) --------------------------------------
    'db.host'        => App\Config::env('DB_HOST', '127.0.0.1'),
    'db.port'        => App\Config::env('DB_PORT', '3306'),
    'db.name'        => App\Config::env('DB_DATABASE', 'ipharmalink'),
    'db.user'        => App\Config::env('DB_USERNAME', 'root'),
    'db.password'    => App\Config::env('DB_PASSWORD', ''),
    'db.charset'     => 'utf8mb4',
    'db.collation'   => 'utf8mb4_unicode_ci',

    // ---- Session security -------------------------------------------------
    'session.name'       => App\Config::env('SESSION_NAME', 'ipharmalink_sid'),
    'session.lifetime'   => App\Config::env('SESSION_LIFETIME', '3600'),  // idle timeout, seconds
    'session.secure'     => App\Config::env('SESSION_SECURE', 'auto'),   // auto => HTTPS only
    'session.samesite'   => 'Lax',
    'session.path'       => '/',
    'session.domain'     => App\Config::env('SESSION_DOMAIN', ''),

    // ---- Security ---------------------------------------------------------
    'security.app_key_fallback' => 'CHANGE-ME-IN-.ENV',
    'security.max_login_attempts' => 5,
    'security.lockout_minutes'   => 15,
    'security.password_min_length' => 8,
    'security.csrf_token_name'    => '_token',
    'security.session_regen_on_login' => true,
    'security.rate_limits' => [
        'login'       => ['max' => 10,  'minutes' => 5],
        'register'    => ['max' => 5,   'minutes' => 30],
        'search'      => ['max' => 60,  'minutes' => 5],
        'api'         => ['max' => 120, 'minutes' => 5],
        'contact'     => ['max' => 3,   'minutes' => 60],
        'password_reset' => ['max' => 3, 'minutes' => 60],
    ],

    // ---- Uploads ----------------------------------------------------------
    'uploads.path'         => APP_ROOT . '/storage/uploads',
    'uploads.max_size_mb'  => 5,
    'uploads.image_mimes'  => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
    'uploads.doc_mimes'    => ['application/pdf', 'image/jpeg', 'image/png'],
    'uploads.image_max'    => ['width' => 2000, 'height' => 2000],

    // ---- Payment gateways (credentials belong in the admin panel / .env) ---
    'payments.paystack_secret_key'    => App\Config::env('PAYSTACK_SECRET_KEY', ''),
    'payments.paystack_public_key'    => App\Config::env('PAYSTACK_PUBLIC_KEY', ''),
    'payments.paystack_webhook_secret' => App\Config::env('PAYSTACK_WEBHOOK_SECRET', ''),
    'payments.flutterwave_secret_key' => App\Config::env('FLW_SECRET_KEY', ''),
    'payments.flutterwave_public_key' => App\Config::env('FLW_PUBLIC_KEY', ''),
    'payments.flutterwave_hash_secret' => App\Config::env('FLW_HASH_SECRET', ''),

    // ---- Mail (SMTP via PHP mail() or a configured transport) -------------
    'mail.driver'      => App\Config::env('MAIL_DRIVER', 'log'),
    'mail.host'        => App\Config::env('MAIL_HOST', ''),
    'mail.port'        => App\Config::env('MAIL_PORT', '587'),
    'mail.username'    => App\Config::env('MAIL_USERNAME', ''),
    'mail.password'    => App\Config::env('MAIL_PASSWORD', ''),
    'mail.from_email'  => App\Config::env('MAIL_FROM_EMAIL', 'no-reply@ipharmalink.ng'),
    'mail.from_name'   => App\Config::env('MAIL_FROM_NAME', 'iPharmaLink'),

    // ---- SMS (provider-agnostic; plug in Termii, BulkSMS, Arkesel…) -------
    'sms.driver'       => App\Config::env('SMS_DRIVER', 'log'),
    'sms.api_key'      => App\Config::env('SMS_API_KEY', ''),
    'sms.sender_id'    => App\Config::env('SMS_SENDER_ID', 'iPharmaLink'),

    // ---- Business defaults (overridable in platform_settings) -------------
    'business.free_delivery_threshold' => 30000.00,   // ₦
    'business.default_delivery_fee'    => 1500.00,    // ₦
    'business.commission_rate'         => 8.00,       // %
    'business.payout_minimum'          => 20000.00,   // ₦
    'business.low_stock_threshold'     => 10,
    'business.expiry_alert_days'       => 90,
    'business.max_qty_per_order'       => 99,
];
