<?php

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

test('can create payment intent for served order', function () {
    $order = DB::table('orders')->first();
    $useCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $useCase->execute((int) $order->id);

    expect($intent['status'])->toBe('pending');
    expect((float) $intent['amount'])->toBe((float) $order->total);
    expect($intent['hmac_signature'])->toBeString()->toHaveLength(64);
    expect($intent['nonce'])->toBeString();
});

test('duplicate intent returns existing', function () {
    $order = DB::table('orders')->first();
    $useCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);

    $intent1 = $useCase->execute((int) $order->id);
    $intent2 = $useCase->execute((int) $order->id);

    expect($intent1['id'])->toBe($intent2['id']);
});

test('can confirm payment and settle order', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    $confirmUseCase = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
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

    $updatedOrder = DB::table('orders')->find($order->id);
    expect($updatedOrder->status)->toBe('settled');
});

test('idempotent confirmation returns existing', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    $confirmUseCase = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    $result1 = $confirmUseCase->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order->version,
    );
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

test('reconcile command creates tickets for stuck orders', function () {
    $order = DB::table('orders')->first();
    $createUseCase = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createUseCase->execute((int) $order->id);

    DB::table('payment_intents')
        ->where('id', $intent['id'])
        ->update(['status' => 'confirmed']);

    DB::table('orders')->where('id', $order->id)->update(['status' => 'served']);

    $this->artisan('harborbite:reconcile-payments')
        ->assertExitCode(0);

    $ticket = DB::table('incident_tickets')
        ->where('order_id', $order->id)
        ->where('type', 'paid_not_settled')
        ->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe('open');
});
