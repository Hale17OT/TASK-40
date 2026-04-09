<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 50.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 50.00, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('cashier cannot apply discount (role restricted)', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(2))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 5.00,
            'expected_version' => (int) $order['version'],
        ])
        ->assertStatus(403);
});

test('manager can apply small discount without PIN', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 10.00,
            'expected_version' => (int) $order['version'],
        ])
        ->assertStatus(200);

    $updated = DB::table('orders')->find($order['id']);
    expect((float) $updated->discount)->toBe(10.00);
    expect((int) $updated->version)->toBe((int) $order['version'] + 1);
});

test('discount > $20 requires manager PIN', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 25.00,
            'expected_version' => (int) $order['version'],
        ])
        ->assertStatus(403)
        ->assertJsonFragment(['error_code' => 'STEP_UP_REQUIRED']);
});

test('discount > $20 succeeds with correct PIN', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 25.00,
            'expected_version' => (int) $order['version'],
            'manager_pin' => '1234',
            'reason' => 'Customer complaint',
        ])
        ->assertStatus(200);

    $updated = DB::table('orders')->find($order['id']);
    expect((float) $updated->discount)->toBe(25.00);

    $log = DB::table('privilege_escalation_logs')
        ->where('action', 'discount_override')
        ->where('order_id', $order['id'])
        ->first();
    expect($log)->not->toBeNull();
});

test('discount > $20 fails with wrong PIN', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 25.00,
            'expected_version' => (int) $order['version'],
            'manager_pin' => '0000',
        ])
        ->assertStatus(403)
        ->assertJsonFragment(['error_code' => 'STEP_UP_FAILED']);
});

test('discount with stale expected_version returns 409', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    // Apply first discount — bumps version
    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 5.00,
            'expected_version' => (int) $order['version'],
        ])
        ->assertStatus(200);

    // Try again with stale version
    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 3.00,
            'expected_version' => (int) $order['version'], // stale!
        ])
        ->assertStatus(409)
        ->assertJsonFragment(['error_code' => 'STALE_VERSION']);
});

test('discount requires expected_version field', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $this->actingAs(User::find(3))
        ->postJson("/api/orders/{$order['id']}/discount", [
            'amount' => 5.00,
            // missing expected_version
        ])
        ->assertStatus(422);
});
