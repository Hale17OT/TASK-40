<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'registrations_per_hour' => (int) env('RATE_LIMIT_REGISTRATIONS', 10),
        'checkouts_per_10_min' => (int) env('RATE_LIMIT_CHECKOUTS', 30),
        'general_per_minute' => (int) env('RATE_LIMIT_GENERAL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Triggers
    |--------------------------------------------------------------------------
    */
    'captcha' => [
        'failed_login_threshold' => (int) env('CAPTCHA_FAILED_LOGINS', 5),
        'rapid_repricing_threshold' => (int) env('CAPTCHA_RAPID_REPRICING', 3),
        'rapid_repricing_window_seconds' => (int) env('CAPTCHA_REPRICING_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | HMAC / Payment Security
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'hmac_key' => env('PAYMENT_HMAC_KEY', ''),
        'hmac_expiry_seconds' => (int) env('PAYMENT_HMAC_EXPIRY', 300), // 5 minutes
        'time_sync_tolerance_seconds' => (int) env('TIME_SYNC_TOLERANCE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Fingerprinting
    |--------------------------------------------------------------------------
    */
    'fingerprint' => [
        'salt' => env('DEVICE_FINGERPRINT_SALT', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search & Menu
    |--------------------------------------------------------------------------
    */
    'search' => [
        'max_trending_terms' => 20,
        'results_per_page' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'max_note_length' => 140,
        'default_tax_rate' => 0.0825, // 8.25% fallback
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts & Observability
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'error_rate_threshold' => 0.05, // 5%
        'risk_hits_per_hour' => 50,
        'gmv_drop_threshold' => 0.50, // 50%
        'failed_logins_per_hour' => 100,
        'latency_p95_ms' => (int) env('ALERT_LATENCY_P95_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Location
    |--------------------------------------------------------------------------
    */
    'location_id' => env('HARBORBITE_LOCATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'America/Chicago'),
];
