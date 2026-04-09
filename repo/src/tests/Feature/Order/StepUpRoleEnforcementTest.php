<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => Hash::make('5555'), 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
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

test('cashier with PIN cannot cancel in_preparation order (role rejected before PIN check)', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);

    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 2);

    // Cashier has a PIN (id=2 has manager_pin) but is NOT manager/admin
    expect(fn () => $transition->execute(
        orderId: (int) $order['id'],
        targetStatus: 'canceled',
        expectedVersion: 2,
        actorRole: 'cashier',
        actorId: 2,
        managerPin: '5555',
        managerPinHash: Hash::make('5555'),
    ))->toThrow(\App\Domain\Order\Exceptions\InsufficientRoleException::class);
});

test('manager with correct PIN CAN cancel in_preparation order', function () {
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);

    $transition->execute((int) $order['id'], 'in_preparation', 1, 'cashier', 2);

    $result = $transition->execute(
        orderId: (int) $order['id'],
        targetStatus: 'canceled',
        expectedVersion: 2,
        actorRole: 'manager',
        actorId: 4,
        managerPin: '1234',
        managerPinHash: Hash::make('1234'),
    );

    expect($result['status'])->toBe('canceled');
});

test('UserManager rejects PIN assignment for cashier role', function () {
    \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'New Cashier')
        ->set('username', 'newcashier')
        ->set('password', 'test123')
        ->set('role', 'cashier')
        ->set('managerPin', '9999')
        ->call('saveUser')
        ->assertSet('error', 'Manager PIN can only be assigned to manager or administrator roles.');

    // User should NOT have been created
    expect(DB::table('users')->where('username', 'newcashier')->exists())->toBeFalse();
});

test('UserManager allows PIN assignment for manager role', function () {
    \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'New Manager')
        ->set('username', 'newmgr')
        ->set('password', 'test123')
        ->set('role', 'manager')
        ->set('managerPin', '4321')
        ->call('saveUser');

    $user = DB::table('users')->where('username', 'newmgr')->first();
    expect($user)->not->toBeNull();
    expect(password_verify('4321', $user->manager_pin))->toBeTrue();
});
