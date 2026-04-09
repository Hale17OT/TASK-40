<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Analytics\TrackEventUseCase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsTrackingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track successful page views (2xx HTML responses)
        if ($response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
            && !$request->is('api/*')
            && !$request->ajax()
        ) {
            try {
                $tracker = new TrackEventUseCase();
                $tracker->execute(
                    eventType: 'page_view',
                    deviceFingerprintId: $request->attributes->get('device_fingerprint_id'),
                    sessionId: session()->getId(),
                    payload: ['path' => $request->path()],
                    traceId: $request->attributes->get('trace_id'),
                );
            } catch (\Throwable) {
                // Fire-and-forget
            }
        }

        return $response;
    }
}
