<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Seed users
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed menu
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Test Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Create a cart with items
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test-session', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('can create order from cart', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    expect($order['status'])->toBe('pending_confirmation');
    expect($order['version'])->toBe(1);
    expect((float) $order['subtotal'])->toBe(25.98);
    expect($order['order_number'])->toStartWith('HB-');

    // Order items created
    $items = DB::table('order_items')->where('order_id', $order['id'])->get();
    expect($items)->toHaveCount(1);
    expect($items[0]->item_name)->toBe('Test Burger');
    expect($items[0]->quantity)->toBe(2);

    // Status log created
    $log = DB::table('order_status_logs')->where('order_id', $order['id'])->first();
    expect($log->to_status)->toBe('pending_confirmation');
});

test('full happy path lifecycle', function () {
    // Create order
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);

    // Cashier confirms -> In Preparation
    $result = $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    expect($result['status'])->toBe('in_preparation');
    expect((int) $result['version'])->toBe(2);

    // Items locked
    $lockedItems = DB::table('order_items')->where('order_id', $orderId)->whereNotNull('locked_at')->count();
    expect($lockedItems)->toBe(1);

    // Kitchen marks served
    $result = $transitionUseCase->execute($orderId, 'served', 2, 'kitchen', 3);
    expect($result['status'])->toBe('served');
    expect((int) $result['version'])->toBe(3);

    // Payment confirmation required before settlement
    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    $confirmPayment = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $settled = $confirmPayment->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: 3,
    );

    expect($settled['order_status'])->toBe('settled');

    // Verify final order state
    $finalOrder = DB::table('orders')->find($orderId);
    expect($finalOrder->status)->toBe('settled');
    expect((int) $finalOrder->version)->toBe(4);

    // Status logs (initial + confirm + served + settled)
    $logs = DB::table('order_status_logs')->where('order_id', $orderId)->orderBy('created_at')->get();
    expect($logs)->toHaveCount(4);
});

test('version conflict is rejected', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);

    // First transition succeeds
    $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);

    // Second transition with stale version fails
    $transitionUseCase->execute($orderId, 'served', 1, 'kitchen', 3);
})->throws(\App\Domain\Order\Exceptions\StaleVersionException::class);

test('cancel from in_preparation requires manager PIN', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);

    // Cancel without PIN fails
    $transitionUseCase->execute($orderId, 'canceled', 2, 'manager', 4);
})->throws(\App\Domain\Order\Exceptions\InsufficientRoleException::class);

test('cancel from pending does not require PIN', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $result = $transitionUseCase->execute($orderId, 'canceled', 1, 'cashier', 2);
    expect($result['status'])->toBe('canceled');
});

test('kitchen cannot confirm orders', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute((int) $order['id'], 'in_preparation', 1, 'kitchen', 3);
})->throws(\App\Domain\Order\Exceptions\InsufficientRoleException::class);

test('checkout page loads', function () {
    $this->get('/checkout')->assertStatus(200);
});

test('staff orders page requires auth', function () {
    $this->get('/staff/orders')->assertRedirect('/login');
});

test('staff orders page accessible when logged in', function () {
    $user = \App\Models\User::find(2);
    $this->actingAs($user)->get('/staff/orders')->assertStatus(200);
});
