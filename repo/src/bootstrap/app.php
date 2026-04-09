<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'fingerprint' => \App\Http\Middleware\DeviceFingerprintMiddleware::class,
            'rate-limit' => \App\Http\Middleware\RateLimitMiddleware::class,
        ]);

        // Make API routes stateful (session/cookie support) so auth guard works
        if (class_exists(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class)) {
            $middleware->statefulApi();
        }

        $middleware->append(\App\Http\Middleware\TraceIdMiddleware::class);
        $middleware->append(\App\Http\Middleware\DeviceFingerprintMiddleware::class);
        $middleware->append(\App\Http\Middleware\RateLimitMiddleware::class);
        $middleware->append(\App\Http\Middleware\RequestLoggingMiddleware::class);
        $middleware->append(\App\Http\Middleware\AnalyticsTrackingMiddleware::class);
        $middleware->append(\App\Http\Middleware\ForcePasswordChangeMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON 401 for all API routes regardless of Accept header
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Authentication required.',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        // Application-level business exceptions → structured 4xx responses
        $exceptions->renderable(function (\App\Application\Exceptions\BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        });

        $exceptions->renderable(function (\App\Domain\Order\Exceptions\PaymentRequiredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'PAYMENT_REQUIRED',
            ], 422);
        });

        $exceptions->renderable(function (\App\Domain\Order\Exceptions\StaleVersionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'STALE_VERSION',
                'current_version' => $e->currentVersion,
                'current_status' => $e->currentStatus,
            ], 409);
        });

        $exceptions->renderable(function (\App\Domain\Order\Exceptions\InvalidTransitionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'INVALID_TRANSITION',
            ], 422);
        });

        $exceptions->renderable(function (\App\Domain\Order\Exceptions\InsufficientRoleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->requiresPin ? 'STEP_UP_REQUIRED' : 'FORBIDDEN',
            ], 403);
        });

        $exceptions->renderable(function (\App\Domain\Order\Exceptions\KitchenLockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'KITCHEN_LOCK',
            ], 403);
        });

        $exceptions->renderable(function (\App\Domain\Payment\Exceptions\ExpiredNonceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'PAYMENT_EXPIRED',
            ], 410);
        });

        $exceptions->renderable(function (\App\Domain\Payment\Exceptions\TamperedSignatureException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'HMAC_FAILED',
            ], 400);
        });

        $exceptions->renderable(function (\App\Domain\Payment\Exceptions\ReplayedNonceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'NONCE_REPLAYED',
            ], 409);
        });

        // Catch enum ValueError from invalid status/type input → 422
        $exceptions->renderable(function (\ValueError $e) {
            if (str_contains($e->getMessage(), 'is not a valid backing value')) {
                return response()->json([
                    'message' => 'Invalid value provided.',
                    'error_code' => 'INVALID_INPUT',
                ], 422);
            }
        });
    })
    ->create();
