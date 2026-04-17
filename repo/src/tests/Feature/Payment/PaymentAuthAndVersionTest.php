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
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

// --- Payment role authorization ---

test('kitchen staff cannot create payment intent (403) with FORBIDDEN error_code', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $response = $this->actingAs(User::find(3))
        ->postJson('/api/payments/intent', ['order_id' => $order['id']]);

    $response->assertStatus(403);
    $response->assertJsonStructure(['message', 'error_code']);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

test('kitchen staff cannot confirm payment (403) with FORBIDDEN error_code', function () {
    $response = $this->actingAs(User::find(3))
        ->postJson('/api/payments/confirm', [
            'reference' => 'test',
            'hmac_signature' => 'test',
            'nonce' => 'test',
            'method' => 'cash',
            'expected_version' => 1,
        ]);

    $response->assertStatus(403);
    $response->assertJsonStructure(['message', 'error_code']);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

test('cashier can create payment intent returning reference, nonce, and expiry', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $response = $this->actingAs(User::find(2))
        ->postJson('/api/payments/intent', ['order_id' => $order['id']]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => ['reference', 'nonce', 'hmac_signature', 'amount', 'expires_at', 'status'],
    ]);

    $data = $response->json('data');
    expect($data['status'])->toBe('pending');
    expect($data['reference'])->toBeString()->not->toBeEmpty();
    expect($data['nonce'])->toBeString()->not->toBeEmpty();
    expect($data['hmac_signature'])->toBeString()->toHaveLength(64);
    expect((float) $data['amount'])->toBeGreaterThan(0);
});

// --- Payment confirm requires expected_version ---

test('payment confirm requires expected_version field', function () {
    $this->actingAs(User::find(2))
        ->postJson('/api/payments/confirm', [
            'reference' => 'test',
            'hmac_signature' => 'test',
            'nonce' => 'test',
            'method' => 'cash',
            // no expected_version
        ])
        ->assertStatus(422);
});

// --- Payment settle goes through state machine ---

test('payment settle uses OrderStateMachine and rejects stale version', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $orderId = (int) $order['id'];
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transition->execute($orderId, 'served', 2, 'kitchen', 3);

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // Use stale version (1 instead of current 3)
    expect(fn () => $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: 1, // stale!
    ))->toThrow(\App\Domain\Order\Exceptions\StaleVersionException::class);
});

test('payment settle succeeds with correct expected_version', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $orderId = (int) $order['id'];
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transition->execute($orderId, 'served', 2, 'kitchen', 3);

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $result = $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: 3, // correct
    );

    expect($result['order_status'])->toBe('settled');
    $settled = DB::table('orders')->find($orderId);
    expect($settled->status)->toBe('settled');
    expect((int) $settled->version)->toBe(4);
});
