<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('login CAPTCHA trigger creates rule_hit_log entry', function () {
    Cache::flush();

    // Simulate enough failed logins to trigger CAPTCHA
    $fingerprintHash = 'test-hash';
    Cache::put("failed_logins:device:{$fingerprintHash}", 6, now()->addHour());
    Cache::put("failed_logins:ip:127.0.0.1", 6, now()->addHour());

    // Load the login page — this triggers checkCaptchaRequired
    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class);

    // Verify CAPTCHA trigger was logged
    $log = DB::table('rule_hit_logs')
        ->where('type', 'captcha_triggered')
        ->first();

    expect($log)->not->toBeNull();
    $details = json_decode($log->details, true);
    expect($details['trigger'])->toBe('failed_logins');
});

test('repricing CAPTCHA trigger creates rule_hit_log entry', function () {
    Cache::flush();

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Pre-fill repricing events to trigger CAPTCHA
    $sessionId = session()->getId();
    Cache::put("repricing_events:{$sessionId}", [time() - 10, time() - 5, time()], now()->addMinutes(5));

    \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);

    $log = DB::table('rule_hit_logs')
        ->where('type', 'captcha_triggered')
        ->get()
        ->first(function ($row) {
            $details = json_decode($row->details, true);
            return ($details['trigger'] ?? null) === 'rapid_repricing';
        });

    expect($log)->not->toBeNull();
});
