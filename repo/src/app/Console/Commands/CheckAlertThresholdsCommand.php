<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckAlertThresholdsCommand extends Command
{
    protected $signature = 'harborbite:check-alerts';
    protected $description = 'Check metric thresholds and create alerts';

    public function handle(): int
    {
        $this->info('Checking alert thresholds...');

        // Failed logins in last hour
        $failedLogins = DB::table('rule_hit_logs')
            ->where('type', 'login_failure')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $threshold = config('harborbite.alerts.failed_logins_per_hour', 100);
        if ($failedLogins > $threshold) {
            $this->createAlert('high_failed_logins', 'critical',
                "High failed login rate: {$failedLogins} in the last hour (threshold: {$threshold})",
                $threshold, $failedLogins);
        }

        // Risk rule hits in last hour
        $riskHits = DB::table('rule_hit_logs')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $riskThreshold = config('harborbite.alerts.risk_hits_per_hour', 50);
        if ($riskHits > $riskThreshold) {
            $this->createAlert('high_risk_hits', 'warning',
                "High risk rule hit rate: {$riskHits} in the last hour (threshold: {$riskThreshold})",
                $riskThreshold, $riskHits);
        }

        // API error rate in last hour
        $this->checkApiErrorRate();

        // API latency p95 in last hour
        $this->checkApiLatency();

        $this->info('Alert check complete.');
        return self::SUCCESS;
    }

    private function checkApiErrorRate(): void
    {
        try {
            $since = now()->subHour();
            $totalRequests = DB::table('request_metrics')
                ->where('created_at', '>=', $since)
                ->count();

            if ($totalRequests === 0) {
                return;
            }

            $errorRequests = DB::table('request_metrics')
                ->where('created_at', '>=', $since)
                ->where('status_code', '>=', 500)
                ->count();

            $errorRate = $errorRequests / $totalRequests;
            $errorRateThreshold = config('harborbite.alerts.error_rate_threshold', 0.05);

            if ($errorRate > $errorRateThreshold) {
                $pct = round($errorRate * 100, 2);
                $thresholdPct = round($errorRateThreshold * 100, 2);
                $this->createAlert('high_error_rate', 'critical',
                    "API error rate {$pct}% exceeds threshold {$thresholdPct}% ({$errorRequests}/{$totalRequests} requests in last hour)",
                    $errorRateThreshold, $errorRate);
            }
        } catch (\Throwable) {
            // Table may not exist yet
        }
    }

    private function checkApiLatency(): void
    {
        try {
            $since = now()->subHour();
            $latencyThresholdMs = config('harborbite.alerts.latency_p95_ms', 2000);

            $metrics = DB::table('request_metrics')
                ->where('created_at', '>=', $since)
                ->orderBy('duration_ms')
                ->pluck('duration_ms')
                ->toArray();

            if (count($metrics) < 10) {
                return; // Not enough data to compute meaningful percentiles
            }

            // Calculate p95
            $p95Index = (int) ceil(0.95 * count($metrics)) - 1;
            $p95 = $metrics[$p95Index];

            if ($p95 > $latencyThresholdMs) {
                $this->createAlert('high_api_latency', 'warning',
                    "API p95 latency {$p95}ms exceeds threshold {$latencyThresholdMs}ms (last hour, " . count($metrics) . " requests)",
                    $latencyThresholdMs, $p95);
            }
        } catch (\Throwable) {
            // Table may not exist yet
        }
    }

    private function createAlert(string $type, string $severity, string $message, float $threshold, float $actual): void
    {
        // Don't duplicate recent alerts
        $exists = DB::table('admin_alerts')
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHour())
            ->whereNull('acknowledged_by')
            ->exists();

        if (!$exists) {
            DB::table('admin_alerts')->insert([
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'threshold_value' => $threshold,
                'actual_value' => $actual,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::warning('alert_created', [
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
            ]);

            $this->warn("  ALERT [{$severity}]: {$message}");
        }
    }
}
