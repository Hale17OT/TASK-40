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
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('direct settle transition is rejected without confirmed payment', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);

    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);
    $transition->execute((int) $order['id'], 'served', 2, 'kitchen', 2);

    // Attempt direct settle without payment — must be rejected
    expect(fn () => $transition->execute((int) $order['id'], 'settled', 3, 'cashier', 1))
        ->toThrow(\App\Domain\Order\Exceptions\PaymentRequiredException::class);
});

test('settle via API is rejected without confirmed payment', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);

    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 1);
    $transition->execute((int) $order['id'], 'served', 2, 'kitchen', 2);

    $this->actingAs(User::find(1))
        ->postJson("/api/orders/{$order['id']}/transition", [
            'target_status' => 'settled',
            'expected_version' => 3,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error_code' => 'PAYMENT_REQUIRED']);
});

test('settle succeeds via payment confirmation workflow', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $orderId = (int) $order['id'];
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);

    $transition->execute($orderId, 'in_preparation', 1, 'cashier', 1);
    $transition->execute($orderId, 'served', 2, 'kitchen', 2);

    // Create and confirm payment
    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    $confirmPayment = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $result = $confirmPayment->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 1,
        actorRole: 'cashier',
        expectedVersion: 3,
    );

    expect($result['order_status'])->toBe('settled');

    $settled = DB::table('orders')->find($orderId);
    expect($settled->status)->toBe('settled');
});
