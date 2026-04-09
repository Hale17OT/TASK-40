<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Test Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('POST /api/orders creates order with tracking token', function () {
    // Add item via API to create cart bound to test session
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    $cart = DB::table('carts')->where('session_id', session()->getId())->first();

    $response = $this->postJson('/api/orders', [
        'cart_id' => $cart->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['data', 'tracking_url']);

    $data = $response->json('data');
    expect($data['tracking_token'])->not->toBeNull();
    expect($data['status'])->toBe('pending_confirmation');
});

test('GET /api/orders/{token} returns order by tracking token', function () {
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    $cart = DB::table('carts')->where('session_id', session()->getId())->first();

    $createResponse = $this->postJson('/api/orders', ['cart_id' => $cart->id]);
    $token = $createResponse->json('data.tracking_token');

    $response = $this->getJson("/api/orders/{$token}");
    $response->assertStatus(200);
    $response->assertJsonStructure(['data', 'items', 'status_log']);
});

test('GET /api/orders/{token} returns 404 for invalid token', function () {
    $response = $this->getJson('/api/orders/nonexistent-token');
    $response->assertStatus(404);
});

test('GET /api/time-sync returns server time', function () {
    $response = $this->getJson('/api/time-sync');
    $response->assertStatus(200);
    $response->assertJsonStructure(['server_time', 'server_time_iso', 'timezone']);
});
