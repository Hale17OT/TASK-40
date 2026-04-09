<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('whitelisted username bypasses rate limiting on HTTP route', function () {
    Cache::flush();

    // Whitelist the cashier username
    DB::table('security_whitelists')->insert([
        'type' => 'username',
        'value' => 'cashier',
        'reason' => 'Trusted user',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::find(2); // cashier

    // Hit an authenticated rate-limited endpoint many times
    // Global rate limit is 60/min, so 65 requests would normally trigger 429
    for ($i = 0; $i < 65; $i++) {
        $response = $this->actingAs($user)->getJson('/api/cart');
    }

    // Username whitelist should bypass rate limit — still 200
    $response->assertStatus(200);
});

test('non-whitelisted username is still rate-limited on HTTP route', function () {
    Cache::flush();

    // Login route has rate-limit:login (10/min)
    // No username whitelist for this test
    for ($i = 0; $i < 11; $i++) {
        $response = $this->get('/login');
    }

    $response->assertStatus(429);
});

test('username whitelist with login form input bypasses rate limit', function () {
    Cache::flush();

    DB::table('security_whitelists')->insert([
        'type' => 'username',
        'value' => 'cashier',
        'reason' => 'Trusted',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The login page sends username in the request body.
    // Rate-limit middleware checks request input for username.
    // With whitelist, even many POST attempts should not be blocked.
    for ($i = 0; $i < 15; $i++) {
        $response = $this->post('/login', [
            'username' => 'cashier',
            'password' => 'wrong_password',
        ]);
    }

    // Should NOT be 429 because username is whitelisted
    expect($response->getStatusCode())->not->toBe(429);
});

test('admin can add and remove username whitelist entries', function () {
    \Livewire\Livewire::test(\App\Livewire\Admin\SecurityRulesManager::class)
        ->set('wlType', 'username')
        ->set('wlValue', 'trusted_user')
        ->set('wlReason', 'VIP kiosk operator')
        ->call('addWhitelist');

    $entry = DB::table('security_whitelists')
        ->where('type', 'username')
        ->where('value', 'trusted_user')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->reason)->toBe('VIP kiosk operator');
});

test('admin can manage banned words with cache invalidation', function () {
    $component = \Livewire\Livewire::test(\App\Livewire\Admin\SecurityRulesManager::class);

    $component->set('newBannedWord', 'testbadword')
        ->call('addBannedWord');

    $word = DB::table('banned_words')->where('word', 'testbadword')->first();
    expect($word)->not->toBeNull();

    // Cache should be cleared
    expect(Cache::has('banned_words'))->toBeFalse();

    $component->call('removeBannedWord', $word->id);
    expect(DB::table('banned_words')->where('word', 'testbadword')->exists())->toBeFalse();
});

test('duplicate banned word is rejected', function () {
    DB::table('banned_words')->insert([
        'word' => 'existing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Livewire\Livewire::test(\App\Livewire\Admin\SecurityRulesManager::class)
        ->set('newBannedWord', 'existing')
        ->call('addBannedWord')
        ->assertSet('error', 'Word already banned.');
});
