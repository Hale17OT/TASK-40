<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Set application timezone from config
        date_default_timezone_set(config('harborbite.timezone', 'America/Chicago'));

        // Fail-fast: critical security secrets must not be empty in non-test environments
        if (!$this->app->environment('testing')) {
            $hmacKey = config('harborbite.payment.hmac_key', '');
            $fingerprintSalt = config('harborbite.fingerprint.salt', '');

            if (empty($hmacKey)) {
                throw new \RuntimeException(
                    'PAYMENT_HMAC_KEY is not set. Payment HMAC signing will be insecure. '
                    . 'Set PAYMENT_HMAC_KEY in your .env file.'
                );
            }

            if (empty($fingerprintSalt)) {
                throw new \RuntimeException(
                    'DEVICE_FINGERPRINT_SALT is not set. Device fingerprinting will be weak. '
                    . 'Set DEVICE_FINGERPRINT_SALT in your .env file.'
                );
            }
        }
    }
}
