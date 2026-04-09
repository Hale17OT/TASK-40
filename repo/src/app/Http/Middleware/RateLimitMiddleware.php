<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, string $action = 'general', int $maxAttempts = 0, int $windowMinutes = 0): Response
    {
        // Always resolve from config by action key; explicit params override config values.
        $configLimits = match ($action) {
            'registration' => [
                'max' => (int) config('harborbite.rate_limits.registrations_per_hour', 10),
                'window' => 60,
            ],
            'checkout' => [
                'max' => (int) config('harborbite.rate_limits.checkouts_per_10_min', 30),
                'window' => 10,
            ],
            'login' => [
                'max' => 10,
                'window' => 1,
            ],
            default => [
                'max' => (int) config('harborbite.rate_limits.general_per_minute', 60),
                'window' => 1,
            ],
        };

        $maxAttempts = $maxAttempts > 0 ? $maxAttempts : $configLimits['max'];
        $windowMinutes = $windowMinutes > 0 ? $windowMinutes : $configLimits['window'];

        // Check whitelist first (device, IP CIDR, or username)
        $fingerprintHash = $request->attributes->get('device_fingerprint_hash');
        if ($fingerprintHash && $this->isWhitelisted('device', $fingerprintHash)) {
            return $next($request);
        }
        if ($request->ip() && $this->isWhitelisted('ip', $request->ip())) {
            return $next($request);
        }
        $username = $request->input('username') ?? auth()->user()?->username ?? null;
        if ($username && $this->isUsernameWhitelisted($username)) {
            return $next($request);
        }

        // Per-device rate limit
        if ($fingerprintHash) {
            $deviceKey = "rate_limit:device:{$action}:{$fingerprintHash}";
            $deviceCount = (int) Cache::get($deviceKey, 0);

            if ($deviceCount >= $maxAttempts) {
                $this->logRateLimit($request, 'device', $action);
                return $this->tooManyRequestsResponse($windowMinutes);
            }

            Cache::put($deviceKey, $deviceCount + 1, now()->addMinutes($windowMinutes));
        }

        // Per-IP rate limit
        $ip = $request->ip();
        if ($ip) {
            $ipKey = "rate_limit:ip:{$action}:{$ip}";
            $ipCount = (int) Cache::get($ipKey, 0);

            if ($ipCount >= $maxAttempts) {
                $this->logRateLimit($request, 'ip', $action);
                return $this->tooManyRequestsResponse($windowMinutes);
            }

            Cache::put($ipKey, $ipCount + 1, now()->addMinutes($windowMinutes));
        }

        return $next($request);
    }

    private function isWhitelisted(string $type, string $value): bool
    {
        if ($type === 'ip') {
            return $this->isIpWhitelisted($value);
        }

        return DB::table('security_whitelists')
            ->where('type', $type)
            ->where('value', $value)
            ->exists();
    }

    private function isUsernameWhitelisted(string $username): bool
    {
        return DB::table('security_whitelists')
            ->where('type', 'username')
            ->where('value', $username)
            ->exists();
    }

    private function isIpWhitelisted(string $ip): bool
    {
        $entries = DB::table('security_whitelists')
            ->where('type', 'ip')
            ->pluck('value');

        foreach ($entries as $entry) {
            if ($this->ipMatchesCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        if ($mask === 0) {
            return true;
        }

        $maskLong = ~((1 << (32 - $mask)) - 1);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    private function logRateLimit(Request $request, string $limitType, string $action): void
    {
        try {
            DB::table('rule_hit_logs')->insert([
                'type' => 'rate_limit',
                'device_fingerprint_id' => $request->attributes->get('device_fingerprint_id'),
                'ip_address' => $request->ip(),
                'details' => json_encode([
                    'limit_type' => $limitType,
                    'action' => $action,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Don't let logging failures block the response
        }
    }

    private function tooManyRequestsResponse(int $windowMinutes): Response
    {
        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'error_code' => 'RATE_LIMITED',
        ], 429)->withHeaders([
            'Retry-After' => $windowMinutes * 60,
        ]);
    }
}
