<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('whitelisted IP CIDR range bypasses rate limiting', function () {
    Cache::flush();

    // Whitelist the CIDR range that includes 127.0.0.1
    DB::table('security_whitelists')->insert([
        'type' => 'ip',
        'value' => '127.0.0.0/8',
        'reason' => 'Trusted local network',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Even after many requests, should not be rate limited (CIDR match)
    for ($i = 0; $i < 15; $i++) {
        $response = $this->get('/login');
    }

    // Should still succeed — the /8 CIDR covers 127.0.0.1
    $response->assertStatus(200);
});

test('exact IP whitelist also works', function () {
    Cache::flush();

    DB::table('security_whitelists')->insert([
        'type' => 'ip',
        'value' => '127.0.0.1',
        'reason' => 'Trusted kiosk',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < 15; $i++) {
        $response = $this->get('/login');
    }

    $response->assertStatus(200);
});

test('non-matching CIDR does not bypass rate limiting', function () {
    Cache::flush();

    DB::table('security_whitelists')->insert([
        'type' => 'ip',
        'value' => '10.0.0.0/8',
        'reason' => 'Different network',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 127.0.0.1 is NOT in 10.0.0.0/8, so rate limiting should apply
    for ($i = 0; $i < 11; $i++) {
        $response = $this->get('/login');
    }

    $response->assertStatus(429);
});
