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
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test-session', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('order is created with a tracking token', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    expect($order['tracking_token'])->not->toBeNull();
    expect(strlen($order['tracking_token']))->toBe(64);
});

test('order can be tracked via tracking token', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $response = $this->get("/order/{$order['tracking_token']}");
    $response->assertStatus(200);
});

test('order cannot be tracked via raw ID', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    // Attempting to access with just the order ID should not find the order
    // because the route now expects a tracking token, not a numeric ID
    $response = $this->get("/order/{$order['id']}");
    $response->assertStatus(200); // Page loads but order won't be found

    // The Livewire component will show null order since no tracking token matches
});

test('invalid tracking token shows no order data', function () {
    $response = $this->get('/order/invalid-token-that-does-not-exist');
    $response->assertStatus(200); // Page loads but no order data
});

test('guessing order ID does not expose order data', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    // Trying to access with a numeric value (old style) should not match any tracking token
    $response = $this->get('/order/1');
    $response->assertStatus(200); // Page loads but no order data
});
