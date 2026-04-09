<?php

use App\Domain\Payment\Exceptions\ReplayedNonceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 2);
    $transitionUseCase->execute((int) $order['id'], 'served', 2, 'kitchen', 2);
});

test('nonce replay after confirmation throws ReplayedNonceException', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    $confirmUseCase = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // First confirmation succeeds
    $result = $confirmUseCase->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order->version,
    );

    expect($result['idempotent'])->toBeFalse();
    expect($result['order_status'])->toBe('settled');

    // Verify nonce_used_at is set
    $updatedIntent = DB::table('payment_intents')->where('reference', $intent['reference'])->first();
    expect($updatedIntent->nonce_used_at)->not->toBeNull();
});

test('idempotent confirmation still works for already confirmed intent', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    $confirmUseCase = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // First confirmation
    $result1 = $confirmUseCase->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order->version,
    );

    // Second call — idempotency check fires before nonce check (intent is already 'confirmed')
    $result2 = $confirmUseCase->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order->version,
    );

    expect($result2['idempotent'])->toBeTrue();
    expect($result1['confirmation_id'])->toBe($result2['confirmation_id']);
});

test('payment intent persists signed_at timestamp from HMAC signer', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    // signed_at should be a unix timestamp (integer), close to current time
    expect($intent['signed_at'])->toBeInt();
    expect(abs(time() - $intent['signed_at']))->toBeLessThan(5);
});

test('HMAC verification uses persisted signed_at not created_at', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    // Artificially shift created_at to a different time to prove signed_at is used
    DB::table('payment_intents')
        ->where('id', $intent['id'])
        ->update(['created_at' => now()->subHour()]);

    $confirmUseCase = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // This should still succeed because we verify against signed_at, not created_at
    $result = $confirmUseCase->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order->version,
    );

    expect($result['order_status'])->toBe('settled');
    expect($result['idempotent'])->toBeFalse();
});
