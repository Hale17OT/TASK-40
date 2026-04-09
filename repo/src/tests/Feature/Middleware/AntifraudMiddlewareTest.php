<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('device fingerprint middleware generates fingerprint on request', function () {
    $response = $this->get('/');
    $response->assertStatus(200);

    // A device fingerprint should have been created
    expect(DB::table('device_fingerprints')->count())->toBeGreaterThanOrEqual(1);
});

test('blacklisted device gets 403', function () {
    // First make a request to generate a fingerprint
    $this->get('/');

    $fingerprint = DB::table('device_fingerprints')->first();
    expect($fingerprint)->not->toBeNull();

    // Blacklist the device
    DB::table('security_blacklists')->insert([
        'type' => 'device',
        'value' => $fingerprint->fingerprint_hash,
        'reason' => 'Suspicious device',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Next request from same fingerprint should be blocked
    $response = $this->get('/');
    $response->assertStatus(403);

    // Rule hit log should be recorded
    $hitLog = DB::table('rule_hit_logs')->where('type', 'blacklist_block')->first();
    expect($hitLog)->not->toBeNull();
});

test('blacklisted IP gets 403', function () {
    DB::table('security_blacklists')->insert([
        'type' => 'ip',
        'value' => '127.0.0.1',
        'reason' => 'Suspicious IP',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->get('/');
    $response->assertStatus(403);
});

test('expired blacklist does not block', function () {
    DB::table('security_blacklists')->insert([
        'type' => 'ip',
        'value' => '127.0.0.1',
        'reason' => 'Expired ban',
        'expires_at' => now()->subDay(),
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->get('/');
    $response->assertStatus(200);
});

test('rate limit returns 429 when exceeded', function () {
    Cache::flush();

    // The login route has rate-limit:login,10,1 middleware
    // Exceed the limit
    for ($i = 0; $i < 11; $i++) {
        $response = $this->get('/login');
    }

    // The 11th request should be rate limited
    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

test('rate limit logs rule hit on exceed', function () {
    Cache::flush();

    for ($i = 0; $i < 11; $i++) {
        $this->get('/login');
    }

    $hitLog = DB::table('rule_hit_logs')->where('type', 'rate_limit')->first();
    expect($hitLog)->not->toBeNull();
});

test('whitelisted device bypasses rate limit', function () {
    Cache::flush();

    // Make a request to generate fingerprint
    $this->get('/');
    $fingerprint = DB::table('device_fingerprints')->first();

    // Whitelist the device
    DB::table('security_whitelists')->insert([
        'type' => 'device',
        'value' => $fingerprint->fingerprint_hash,
        'reason' => 'Trusted kiosk',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Even after many requests, should not be rate limited
    for ($i = 0; $i < 15; $i++) {
        $response = $this->get('/login');
    }

    $response->assertStatus(200);
});
