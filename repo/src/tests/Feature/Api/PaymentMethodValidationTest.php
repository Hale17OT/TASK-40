<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
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

    // Create order and transition to 'served' so payment can be confirmed
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);
    $transition->execute((int) $order['id'], 'served', 2, 'kitchen', 2);
});

test('payment confirm rejects unknown payment method', function () {
    $this->actingAs(User::find(1))
        ->postJson('/api/payments/confirm', [
            'reference' => 'test-ref',
            'hmac_signature' => 'test-sig',
            'nonce' => 'test-nonce',
            'method' => 'bitcoin',
            'expected_version' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['method']);
});

test('payment confirm accepts cash method', function () {
    $order = DB::table('orders')->first();

    $intentUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $intentUseCase->execute((int) $order->id);

    $this->actingAs(User::find(1))
        ->postJson('/api/payments/confirm', [
            'reference' => $intent['reference'],
            'hmac_signature' => $intent['hmac_signature'],
            'nonce' => $intent['nonce'],
            'method' => 'cash',
            'expected_version' => (int) $order->version,
        ])
        ->assertStatus(201);
});

test('payment confirm accepts card_manual method', function () {
    $order = DB::table('orders')->first();

    $intentUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $intentUseCase->execute((int) $order->id);

    $this->actingAs(User::find(1))
        ->postJson('/api/payments/confirm', [
            'reference' => $intent['reference'],
            'hmac_signature' => $intent['hmac_signature'],
            'nonce' => $intent['nonce'],
            'method' => 'card_manual',
            'expected_version' => (int) $order->version,
        ])
        ->assertStatus(201);
});

test('payment confirm rejects empty method', function () {
    $this->actingAs(User::find(1))
        ->postJson('/api/payments/confirm', [
            'reference' => 'test-ref',
            'hmac_signature' => 'test-sig',
            'nonce' => 'test-nonce',
            'method' => '',
            'expected_version' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['method']);
});
