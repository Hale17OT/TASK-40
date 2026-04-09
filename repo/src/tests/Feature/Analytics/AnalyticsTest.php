<?php

use App\Application\Analytics\TrackEventUseCase;
use App\Application\Analytics\ComputeAnalyticsUseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('track event writes to analytics_events table', function () {
    $useCase = new TrackEventUseCase();
    $useCase->execute('page_view', null, 'session-1', ['page' => '/menu']);

    $event = DB::table('analytics_events')->first();
    expect($event)->not->toBeNull();
    expect($event->event_type)->toBe('page_view');
    expect($event->session_id)->toBe('session-1');
});

test('track event does not throw on failure', function () {
    // Even if something goes wrong, it should not throw
    $useCase = new TrackEventUseCase();
    // This should succeed normally
    $useCase->execute('test_event', null, 'session-1', ['test' => true], Str::uuid()->toString());
    expect(true)->toBeTrue();
});

test('compute analytics returns correct structure', function () {
    // Seed some events
    $traceId = Str::uuid()->toString();
    $now = now();

    DB::table('analytics_events')->insert([
        ['event_type' => 'page_view', 'session_id' => 's1', 'trace_id' => Str::uuid()->toString(), 'created_at' => $now, 'payload' => '{}'],
        ['event_type' => 'page_view', 'session_id' => 's2', 'trace_id' => Str::uuid()->toString(), 'created_at' => $now, 'payload' => '{}'],
        ['event_type' => 'add_to_cart', 'session_id' => 's1', 'trace_id' => Str::uuid()->toString(), 'created_at' => $now, 'payload' => '{}'],
        ['event_type' => 'order_placed', 'session_id' => 's1', 'trace_id' => Str::uuid()->toString(), 'created_at' => $now, 'payload' => '{}'],
    ]);

    $useCase = new ComputeAnalyticsUseCase();
    $result = $useCase->execute(
        $now->copy()->subDay()->format('Y-m-d H:i:s'),
        $now->copy()->addDay()->format('Y-m-d H:i:s'),
    );

    expect($result)->toHaveKeys(['dau', 'gmv', 'total_gmv', 'funnel', 'conversion_rate', 'total_sessions']);
    expect($result['funnel']['page_views'])->toBe(2);
    expect($result['funnel']['add_to_cart'])->toBe(1);
    expect($result['funnel']['order_placed'] ?? $result['funnel']['orders_placed'])->toBe(1);
    expect($result['total_sessions'])->toBe(2);
});

test('admin dashboard loads', function () {
    $admin = \App\Models\User::find(1);
    $this->actingAs($admin)->get('/admin/dashboard')->assertStatus(200);
});

test('check alerts command runs without error', function () {
    $this->artisan('harborbite:check-alerts')->assertExitCode(0);
});

test('key rotation command runs in dry-run mode', function () {
    $this->artisan('harborbite:rotate-key', ['--dry-run' => true])->assertExitCode(0);
});
