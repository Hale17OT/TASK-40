<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 10.00, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('invalid target_status returns 422 with validation error not 500', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(1))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'nonexistent_status',
            'expected_version' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_status']);
});

test('valid target_status values are accepted by validation', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    // 'in_preparation' is valid — should not get validation error
    $this->actingAs(User::find(1))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(200);
});

test('transition on non-existent order returns 404 not 500', function () {
    $this->actingAs(User::find(1))
        ->postJson('/api/orders/99999/transition', [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(404);
});

test('payment intent for non-existent order returns 404 not 500', function () {
    $this->actingAs(User::find(1))
        ->postJson('/api/payments/intent', ['order_id' => 99999])
        ->assertStatus(404);
});

test('payment intent for settled order returns 422 not 500', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    DB::table('orders')->where('id', $order['id'])->update(['status' => 'settled']);

    $this->actingAs(User::find(1))
        ->postJson('/api/payments/intent', ['order_id' => $order['id']])
        ->assertStatus(422);
});

test('BusinessException returns structured JSON with error_code', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    DB::table('orders')->where('id', $order['id'])->update(['status' => 'canceled']);

    $this->actingAs(User::find(1))
        ->postJson('/api/payments/intent', ['order_id' => $order['id']])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'error_code']);
});
