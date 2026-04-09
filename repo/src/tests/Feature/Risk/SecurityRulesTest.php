<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('admin can access security page', function () {
    $admin = \App\Models\User::find(1);
    $this->actingAs($admin)->get('/admin/security')->assertStatus(200);
});

test('non-admin cannot access security page', function () {
    $cashier = \App\Models\User::find(2);
    $this->actingAs($cashier)->get('/admin/security')->assertStatus(403);
});

test('blacklist entry can be created', function () {
    DB::table('security_blacklists')->insert([
        'type' => 'ip',
        'value' => '192.168.1.0/24',
        'reason' => 'Suspicious activity',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entry = DB::table('security_blacklists')->first();
    expect($entry->type)->toBe('ip');
    expect($entry->value)->toBe('192.168.1.0/24');
});

test('whitelist entry can be created', function () {
    DB::table('security_whitelists')->insert([
        'type' => 'device',
        'value' => 'abc123hash',
        'reason' => 'Trusted kiosk',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entry = DB::table('security_whitelists')->first();
    expect($entry->type)->toBe('device');
    expect($entry->value)->toBe('abc123hash');
});

test('rule hit log is immutable via application layer', function () {
    DB::table('rule_hit_logs')->insert([
        'type' => 'test',
        'ip_address' => '127.0.0.1',
        'details' => json_encode(['test' => true]),
        'created_at' => now(),
    ]);

    $log = \App\Infrastructure\Persistence\Models\RuleHitLog::first();
    expect(fn () => $log->update(['type' => 'changed']))->toThrow(\RuntimeException::class);
});
