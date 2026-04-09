<?php

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
});

test('confirmed_by is set when transitioning to InPreparation', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $result = $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);

    expect((int) $result['confirmed_by'])->toBe(1);
});

test('served_by is set correctly when transitioning to Served', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);
    $result = $transition->execute((int) $order['id'], 'served', 2, 'kitchen', 2);

    // CRITICAL: must be served_by, not prepared_by
    expect((int) $result['served_by'])->toBe(2);
    expect($result['prepared_by'])->toBeNull();
});

test('settled_by is set when transitioning to Settled', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);
    $transition->execute((int) $order['id'], 'served', 2, 'kitchen', 2);

    // Settlement requires a confirmed payment intent
    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute((int) $order['id']);
    $confirmPayment = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $confirmPayment->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 1,
        actorRole: 'cashier',
        expectedVersion: 3,
    );

    $result = (array) DB::table('orders')->find((int) $order['id']);
    expect((int) $result['settled_by'])->toBe(1);
});

test('canceled_by and cancel_reason are set when canceling', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $result = $transition->execute(
        orderId: (int) $order['id'],
        targetStatus: 'canceled',
        expectedVersion: 1,
        actorRole: 'cashier',
        actorId: 1,
        cancelReason: 'Customer changed mind',
    );

    expect((int) $result['canceled_by'])->toBe(1);
    expect($result['cancel_reason'])->toBe('Customer changed mind');
});
