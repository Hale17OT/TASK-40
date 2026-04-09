<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('API 401 returns JSON even without Accept header', function () {
    // Send a plain request (not postJson) to API route without Accept: application/json
    $response = $this->post('/api/payments/intent', ['order_id' => 1]);

    $response->assertStatus(401);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJsonStructure(['message', 'error_code']);
});

test('API 403 role check returns JSON even without Accept header for API paths', function () {
    // Kitchen role should not access payment routes
    $response = $this->actingAs(\App\Models\User::find(2))
        ->post('/api/payments/intent', ['order_id' => 1]);

    $response->assertStatus(403);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJsonStructure(['message', 'error_code']);
});

test('API unauthenticated transition returns JSON 401', function () {
    $response = $this->post('/api/orders/1/transition', [
        'target_status' => 'in_preparation',
        'expected_version' => 1,
    ]);

    $response->assertStatus(401);
    $response->assertHeader('content-type', 'application/json');
});

test('API discount route returns JSON 401 for unauthenticated requests without Accept header', function () {
    $response = $this->post('/api/orders/1/discount', [
        'amount' => 5.00,
        'expected_version' => 1,
    ]);

    $response->assertStatus(401);
    $response->assertHeader('content-type', 'application/json');
});
