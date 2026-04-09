<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $status = $response->getStatusCode();

        Log::channel('daily')->info('request', [
            'trace_id' => $request->attributes->get('trace_id', 'unknown'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'device_fingerprint' => $request->attributes->get('device_fingerprint_hash'),
            'user_id' => $request->user()?->id,
        ]);

        // Persist request metrics for observability aggregation
        try {
            DB::table('request_metrics')->insert([
                'method' => $request->method(),
                'path' => substr($request->path(), 0, 255),
                'status_code' => $status,
                'duration_ms' => $duration,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Don't let metrics persistence block the response
        }

        return $response;
    }
}
