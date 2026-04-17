<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
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
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test-session', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

// --- Unauthenticated access (401) ---

test('POST /api/orders/{id}/transition returns 401 without auth', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->postJson("/api/orders/{$order['id']}/transition", [
        'target_status' => 'in_preparation',
        'expected_version' => 1,
    ])->assertStatus(401);
});

test('POST /api/payments/intent returns 401 without auth', function () {
    $this->postJson('/api/payments/intent', [
        'order_id' => 1,
    ])->assertStatus(401);
});

test('POST /api/payments/confirm returns 401 without auth', function () {
    $this->postJson('/api/payments/confirm', [
        'reference' => 'test',
        'hmac_signature' => 'test',
        'nonce' => 'test',
        'method' => 'cash',
    ])->assertStatus(401);
});

// --- Authenticated access ---

test('POST /api/orders/{id}/transition works for authenticated cashier', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(2))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(200);

    $updated = DB::table('orders')->find($order['id']);
    expect($updated->status)->toBe('in_preparation');
});

test('POST /api/orders/{id}/transition rejects kitchen confirming orders', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(403);
});

test('POST /api/orders/{id}/transition returns 409 on version conflict', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $user = User::find(2);

    // First transition succeeds
    $this->actingAs($user)
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(200);

    // Second transition with stale version fails
    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'served',
            'expected_version' => 1,
        ])
        ->assertStatus(409);
});

test('POST /api/payments/intent works for authenticated staff', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(2))
        ->postJson('/api/payments/intent', [
            'order_id' => $order['id'],
        ])
        ->assertStatus(201);
});

// --- Public endpoints remain accessible ---

test('GET /api/menu/search is accessible without auth and returns search payload', function () {
    $response = $this->getJson('/api/menu/search');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => ['items', 'total'], 'query']);
    expect($response->json('data.items'))->toBeArray();
});

test('POST /api/cart/items is accessible without auth and creates a cart row', function () {
    $response = $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    $response->assertStatus(201);
    $response->assertJsonStructure(['message']);

    $cart = DB::table('carts')->where('session_id', session()->getId())->first();
    expect($cart)->not->toBeNull();
    $items = DB::table('cart_items')->where('cart_id', $cart->id)->get();
    expect($items)->toHaveCount(1);
    expect((int) $items->first()->menu_item_id)->toBe(1);
});

test('POST /api/orders is accessible without auth and returns tracking metadata', function () {
    // Create cart bound to this test session via API
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    $cart = DB::table('carts')->where('session_id', session()->getId())->first();

    $response = $this->postJson('/api/orders', ['cart_id' => $cart->id]);
    $response->assertStatus(201);
    $response->assertJsonStructure(['data' => ['id', 'order_number', 'tracking_token', 'status'], 'tracking_url']);

    expect($response->json('data.status'))->toBe('pending_confirmation');
    expect($response->json('data.tracking_token'))->toBeString()->not->toBeEmpty();
    expect($response->json('tracking_url'))->toContain('/order/');
});
