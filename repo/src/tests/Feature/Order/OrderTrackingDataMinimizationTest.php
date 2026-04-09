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

test('guest tracking endpoint does not expose internal order IDs', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $response = $this->getJson("/api/orders/{$order['tracking_token']}");
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->not->toHaveKey('id');
    expect($data)->not->toHaveKey('cart_id');
    expect($data)->not->toHaveKey('confirmed_by');
    expect($data)->not->toHaveKey('prepared_by');
    expect($data)->not->toHaveKey('served_by');
    expect($data)->not->toHaveKey('settled_by');
    expect($data)->not->toHaveKey('canceled_by');
    expect($data)->not->toHaveKey('cancel_reason');
    expect($data)->not->toHaveKey('version');
    expect($data)->not->toHaveKey('tracking_token');
});

test('guest tracking endpoint only returns guest-safe order fields', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $response = $this->getJson("/api/orders/{$order['tracking_token']}");
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveKeys(['order_number', 'status', 'subtotal', 'tax', 'discount', 'total', 'created_at']);
});

test('guest tracking endpoint returns minimized item data', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $response = $this->getJson("/api/orders/{$order['tracking_token']}");
    $response->assertStatus(200);

    $items = $response->json('items');
    expect($items)->toBeArray()->not->toBeEmpty();

    $item = $items[0];
    expect($item)->toHaveKeys(['item_name', 'quantity', 'unit_price', 'line_total']);
    expect($item)->not->toHaveKey('id');
    expect($item)->not->toHaveKey('order_id');
    expect($item)->not->toHaveKey('menu_item_id');
    expect($item)->not->toHaveKey('item_sku');
    expect($item)->not->toHaveKey('locked_at');
});

test('guest tracking status log does not expose internal actor data', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    // Transition to create a status log entry
    $user = \App\Models\User::find(1);
    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute(
        orderId: $order['id'],
        targetStatus: 'in_preparation',
        expectedVersion: 1,
        actorRole: 'cashier',
        actorId: 1,
    );

    $response = $this->getJson("/api/orders/{$order['tracking_token']}");
    $response->assertStatus(200);

    $statusLog = $response->json('status_log');
    expect($statusLog)->toBeArray()->not->toBeEmpty();

    $logEntry = $statusLog[0];
    expect($logEntry)->toHaveKeys(['status', 'timestamp']);
    expect($logEntry)->not->toHaveKey('id');
    expect($logEntry)->not->toHaveKey('order_id');
    expect($logEntry)->not->toHaveKey('changed_by');
    expect($logEntry)->not->toHaveKey('version_at_change');
    expect($logEntry)->not->toHaveKey('metadata');
});

test('staff detail endpoint returns full order data when authenticated', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $response = $this->actingAs(\App\Models\User::find(1))
        ->getJson("/api/orders/{$order['id']}/detail");

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('cart_id');
    expect($data)->toHaveKey('version');
});

test('staff detail endpoint requires authentication', function () {
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $this->getJson("/api/orders/{$order['id']}/detail")
        ->assertStatus(401);
});
