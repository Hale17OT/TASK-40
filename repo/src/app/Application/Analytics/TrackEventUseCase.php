<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrackEventUseCase
{
    public function execute(
        string $eventType,
        ?int $deviceFingerprintId = null,
        ?string $sessionId = null,
        array $payload = [],
        ?string $traceId = null,
    ): void {
        try {
            DB::table('analytics_events')->insert([
                'event_type' => $eventType,
                'device_fingerprint_id' => $deviceFingerprintId,
                'session_id' => $sessionId,
                'payload' => json_encode($payload),
                'trace_id' => $traceId ?? Str::uuid()->toString(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Fire-and-forget: don't let analytics tracking break the user flow
            \Illuminate\Support\Facades\Log::error('analytics_tracking_failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
