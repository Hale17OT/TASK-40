<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\Risk\DeviceFingerprintGenerator;
use Symfony\Component\HttpFoundation\Response;

class DeviceFingerprintMiddleware
{
    public function __construct(
        private readonly DeviceFingerprintGenerator $generator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent() ?? 'unknown';
        $screenTraits = [];

        if ($request->hasHeader('X-Screen-Width')) {
            $screenTraits['width'] = $request->header('X-Screen-Width');
        }
        if ($request->hasHeader('X-Screen-Height')) {
            $screenTraits['height'] = $request->header('X-Screen-Height');
        }
        if ($request->hasHeader('X-Screen-Color-Depth')) {
            $screenTraits['colorDepth'] = $request->header('X-Screen-Color-Depth');
        }

        $fingerprintHash = $this->generator->generate($userAgent, $screenTraits);

        // Upsert device fingerprint record (race-safe: try insert, handle duplicate)
        $fingerprint = DB::table('device_fingerprints')
            ->where('fingerprint_hash', $fingerprintHash)
            ->first();

        if ($fingerprint) {
            DB::table('device_fingerprints')
                ->where('id', $fingerprint->id)
                ->update(['last_seen_at' => now()]);
            $fingerprintId = $fingerprint->id;
        } else {
            try {
                $fingerprintId = DB::table('device_fingerprints')->insertGetId([
                    'fingerprint_hash' => $fingerprintHash,
                    'user_agent' => encrypt($userAgent),
                    'screen_traits' => encrypt(json_encode($screenTraits)),
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Duplicate key — another request inserted concurrently; fetch the existing row
                $fingerprint = DB::table('device_fingerprints')
                    ->where('fingerprint_hash', $fingerprintHash)
                    ->first();

                if (!$fingerprint) {
                    throw $e; // Not a duplicate key error; re-throw
                }

                DB::table('device_fingerprints')
                    ->where('id', $fingerprint->id)
                    ->update(['last_seen_at' => now()]);
                $fingerprintId = $fingerprint->id;
            }
        }

        $request->attributes->set('device_fingerprint_id', $fingerprintId);
        $request->attributes->set('device_fingerprint_hash', $fingerprintHash);

        // Check blacklist
        $isBlacklisted = DB::table('security_blacklists')
            ->where('type', 'device')
            ->where('value', $fingerprintHash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($isBlacklisted) {
            DB::table('rule_hit_logs')->insert([
                'type' => 'blacklist_block',
                'device_fingerprint_id' => $fingerprintId,
                'ip_address' => $request->ip(),
                'details' => json_encode(['reason' => 'device_blacklisted', 'hash' => $fingerprintHash]),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Access denied.',
                'error_code' => 'BLACKLISTED',
            ], 403);
        }

        // Check IP blacklist
        $ipBlacklisted = $this->checkIpBlacklist($request->ip());
        if ($ipBlacklisted) {
            DB::table('rule_hit_logs')->insert([
                'type' => 'blacklist_block',
                'device_fingerprint_id' => $fingerprintId,
                'ip_address' => $request->ip(),
                'details' => json_encode(['reason' => 'ip_blacklisted']),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Access denied.',
                'error_code' => 'BLACKLISTED',
            ], 403);
        }

        // Check username blacklist (applies to login and authenticated requests)
        $username = $request->input('username') ?? auth()->user()?->username ?? null;
        if ($username && $this->checkUsernameBlacklist($username)) {
            DB::table('rule_hit_logs')->insert([
                'type' => 'blacklist_block',
                'device_fingerprint_id' => $fingerprintId,
                'ip_address' => $request->ip(),
                'details' => json_encode(['reason' => 'username_blacklisted', 'username' => $username]),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Access denied.',
                'error_code' => 'BLACKLISTED',
            ], 403);
        }

        return $next($request);
    }

    private function checkIpBlacklist(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        $entries = DB::table('security_blacklists')
            ->where('type', 'ip')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($entries as $entry) {
            if ($this->ipMatchesCidr($ip, $entry->value)) {
                return true;
            }
        }

        return false;
    }

    private function checkUsernameBlacklist(string $username): bool
    {
        return DB::table('security_blacklists')
            ->where('type', 'username')
            ->where('value', $username)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
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
            return true; // /0 matches all IPs
        }

        $maskLong = ~((1 << (32 - $mask)) - 1);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
