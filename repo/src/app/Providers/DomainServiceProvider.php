<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Auth\StepUpVerifier;
use App\Domain\Order\OrderStateMachine;
use App\Domain\Risk\CaptchaTriggerEvaluator;
use App\Domain\Risk\DeviceFingerprintGenerator;
use App\Domain\Risk\ProfanityFilter;
use App\Domain\Risk\RateLimitEvaluator;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeviceFingerprintGenerator::class, function () {
            return new DeviceFingerprintGenerator(
                salt: config('harborbite.fingerprint.salt', ''),
            );
        });

        $this->app->singleton(CaptchaTriggerEvaluator::class, function () {
            return new CaptchaTriggerEvaluator(
                failedLoginThreshold: config('harborbite.captcha.failed_login_threshold', 5),
                rapidRepricingThreshold: config('harborbite.captcha.rapid_repricing_threshold', 3),
                rapidRepricingWindowSeconds: config('harborbite.captcha.rapid_repricing_window_seconds', 60),
            );
        });

        $this->app->singleton(OrderStateMachine::class, fn () => new OrderStateMachine());
        $this->app->singleton(StepUpVerifier::class, fn () => new StepUpVerifier());
        $this->app->singleton(RateLimitEvaluator::class, fn () => new RateLimitEvaluator());
        $this->app->singleton(ProfanityFilter::class, function () {
            $bannedWords = \Illuminate\Support\Facades\Cache::remember('banned_words', 3600, function () {
                try {
                    return \Illuminate\Support\Facades\DB::table('banned_words')->pluck('word')->toArray();
                } catch (\Throwable) {
                    return [];
                }
            });
            return new ProfanityFilter($bannedWords);
        });

        $this->app->bind(
            \App\Application\Search\Ports\MenuRepositoryInterface::class,
            \App\Infrastructure\Persistence\Repositories\EloquentMenuRepository::class,
        );

        $this->app->singleton(\App\Domain\Search\AllergenFilter::class, fn () => new \App\Domain\Search\AllergenFilter());

        $this->app->singleton(\App\Domain\Promotion\PromotionEvaluator::class, fn () => new \App\Domain\Promotion\PromotionEvaluator());

        $this->app->singleton(\App\Application\Search\SearchMenuUseCase::class, function ($app) {
            return new \App\Application\Search\SearchMenuUseCase(
                $app->make(\App\Application\Search\Ports\MenuRepositoryInterface::class),
                $app->make(\App\Domain\Risk\ProfanityFilter::class),
                $app->make(\App\Domain\Search\AllergenFilter::class),
            );
        });

        $this->app->singleton(\App\Domain\Payment\HmacSigner::class, function () {
            return new \App\Domain\Payment\HmacSigner(
                key: config('harborbite.payment.hmac_key', ''),
                expirySeconds: config('harborbite.payment.hmac_expiry_seconds', 300),
            );
        });

        $this->app->singleton(\App\Application\Payment\CreatePaymentIntentUseCase::class, function ($app) {
            return new \App\Application\Payment\CreatePaymentIntentUseCase(
                $app->make(\App\Domain\Payment\HmacSigner::class),
            );
        });

        $this->app->singleton(\App\Application\Order\TransitionOrderUseCase::class, function ($app) {
            return new \App\Application\Order\TransitionOrderUseCase(
                $app->make(\App\Domain\Order\OrderStateMachine::class),
                $app->make(\App\Domain\Auth\StepUpVerifier::class),
            );
        });

        $this->app->singleton(\App\Application\Payment\ConfirmPaymentUseCase::class, function ($app) {
            return new \App\Application\Payment\ConfirmPaymentUseCase(
                $app->make(\App\Domain\Payment\HmacSigner::class),
                $app->make(\App\Domain\Auth\StepUpVerifier::class),
                $app->make(\App\Application\Order\TransitionOrderUseCase::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
