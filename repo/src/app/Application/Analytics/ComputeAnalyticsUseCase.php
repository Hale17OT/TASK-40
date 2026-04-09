<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use Illuminate\Support\Facades\DB;

class ComputeAnalyticsUseCase
{
    public function execute(string $from, string $to): array
    {
        $events = DB::table('analytics_events')
            ->whereBetween('created_at', [$from, $to]);

        $dau = (clone $events)
            ->selectRaw("DATE(created_at) as day, COUNT(DISTINCT session_id) as count")
            ->groupByRaw("DATE(created_at)")
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $gmvQuery = DB::table('orders')
            ->where('status', 'settled')
            ->whereBetween('updated_at', [$from, $to]);

        $gmv = (clone $gmvQuery)
            ->selectRaw("DATE(updated_at) as day, SUM(total) as total")
            ->groupByRaw("DATE(updated_at)")
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $totalGmv = (clone $gmvQuery)->sum('total');

        // Funnel: page_view -> add_to_cart -> checkout_started -> order_placed -> order_settled
        $funnel = [
            'page_views' => (clone $events)->where('event_type', 'page_view')->count(),
            'add_to_cart' => (clone $events)->where('event_type', 'add_to_cart')->count(),
            'checkout_started' => (clone $events)->where('event_type', 'checkout_started')->count(),
            'orders_placed' => (clone $events)->where('event_type', 'order_placed')->count(),
            'orders_settled' => (clone $events)->where('event_type', 'order_settled')->count(),
        ];

        $totalSessions = DB::table('analytics_events')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('session_id')
            ->count('session_id');

        $conversion = $totalSessions > 0
            ? round($funnel['orders_placed'] / $totalSessions * 100, 2)
            : 0;

        // Retention: sessions that returned within the date range
        // A "returning" session is one that had events on more than one distinct day
        $returningSessions = DB::table('analytics_events')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('session_id')
            ->selectRaw('session_id, COUNT(DISTINCT DATE(created_at)) as active_days')
            ->groupBy('session_id')
            ->having(DB::raw('COUNT(DISTINCT DATE(created_at))'), '>', 1)
            ->get()
            ->count();

        $retentionRate = $totalSessions > 0
            ? round($returningSessions / $totalSessions * 100, 2)
            : 0;

        return [
            'dau' => $dau,
            'gmv' => $gmv,
            'total_gmv' => round((float) $totalGmv, 2),
            'funnel' => $funnel,
            'conversion_rate' => $conversion,
            'total_sessions' => $totalSessions,
            'returning_sessions' => $returningSessions,
            'retention_rate' => $retentionRate,
        ];
    }
}
