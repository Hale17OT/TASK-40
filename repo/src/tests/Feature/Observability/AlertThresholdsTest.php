<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Create request_metrics table if not exists (migration may not have run)
    if (!Schema::hasTable('request_metrics')) {
        Schema::create('request_metrics', function ($table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->float('duration_ms');
            $table->timestamp('created_at');
            $table->index(['created_at', 'status_code']);
        });
    }
});

test('alert command detects high failed logins', function () {
    // Insert many login failures in the last hour
    for ($i = 0; $i < 110; $i++) {
        DB::table('rule_hit_logs')->insert([
            'type' => 'login_failure',
            'ip_address' => '127.0.0.1',
            'details' => json_encode(['test' => true]),
            'created_at' => now()->subMinutes(rand(1, 59)),
        ]);
    }

    $this->artisan('harborbite:check-alerts')->assertExitCode(0);

    $alert = DB::table('admin_alerts')->where('type', 'high_failed_logins')->first();
    expect($alert)->not->toBeNull();
    expect($alert->severity)->toBe('critical');
});

test('alert command detects high API error rate', function () {
    // Insert 100 requests, 10 of which are 500 errors (10% > 5% threshold)
    for ($i = 0; $i < 90; $i++) {
        DB::table('request_metrics')->insert([
            'method' => 'GET',
            'path' => '/api/test',
            'status_code' => 200,
            'duration_ms' => rand(50, 200),
            'created_at' => now()->subMinutes(rand(1, 59)),
        ]);
    }
    for ($i = 0; $i < 10; $i++) {
        DB::table('request_metrics')->insert([
            'method' => 'GET',
            'path' => '/api/test',
            'status_code' => 500,
            'duration_ms' => rand(500, 2000),
            'created_at' => now()->subMinutes(rand(1, 59)),
        ]);
    }

    $this->artisan('harborbite:check-alerts')->assertExitCode(0);

    $alert = DB::table('admin_alerts')->where('type', 'high_error_rate')->first();
    expect($alert)->not->toBeNull();
    expect($alert->severity)->toBe('critical');
});

test('alert command detects high API latency', function () {
    // Insert 20 requests with very high latency
    for ($i = 0; $i < 20; $i++) {
        DB::table('request_metrics')->insert([
            'method' => 'GET',
            'path' => '/api/test',
            'status_code' => 200,
            'duration_ms' => 5000, // 5000ms > 2000ms threshold
            'created_at' => now()->subMinutes(rand(1, 59)),
        ]);
    }

    $this->artisan('harborbite:check-alerts')->assertExitCode(0);

    $alert = DB::table('admin_alerts')->where('type', 'high_api_latency')->first();
    expect($alert)->not->toBeNull();
    expect($alert->severity)->toBe('warning');
});

test('alert command does not duplicate recent alerts', function () {
    for ($i = 0; $i < 110; $i++) {
        DB::table('rule_hit_logs')->insert([
            'type' => 'login_failure',
            'ip_address' => '127.0.0.1',
            'details' => json_encode([]),
            'created_at' => now()->subMinutes(30),
        ]);
    }

    $this->artisan('harborbite:check-alerts');
    $this->artisan('harborbite:check-alerts');

    $count = DB::table('admin_alerts')->where('type', 'high_failed_logins')->count();
    expect($count)->toBe(1);
});

test('no alert created when below thresholds', function () {
    // Only 5 requests, all successful
    for ($i = 0; $i < 5; $i++) {
        DB::table('request_metrics')->insert([
            'method' => 'GET',
            'path' => '/api/test',
            'status_code' => 200,
            'duration_ms' => 100,
            'created_at' => now()->subMinutes(rand(1, 59)),
        ]);
    }

    $this->artisan('harborbite:check-alerts')->assertExitCode(0);

    expect(DB::table('admin_alerts')->count())->toBe(0);
});
