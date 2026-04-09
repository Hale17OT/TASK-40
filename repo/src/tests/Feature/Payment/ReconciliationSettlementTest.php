<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
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

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
        DB::statement("SELECT setval('menu_categories_id_seq', (SELECT MAX(id) FROM menu_categories))");
        DB::statement("SELECT setval('menu_items_id_seq', (SELECT MAX(id) FROM menu_items))");
    }
});

test('resolving reconciliation ticket transitions order to settled', function () {
    // Create order and move to served
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'recon-settle', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transitionUseCase->execute($orderId, 'served', 2, 'kitchen', 2);

    // Create a confirmed payment intent (simulating the paid-not-settled scenario)
    $intentId = DB::table('payment_intents')->insertGetId([
        'order_id' => $orderId,
        'reference' => \Illuminate\Support\Str::uuid(),
        'amount' => 12.99,
        'hmac_signature' => 'test',
        'nonce' => 'test-nonce-settle-' . uniqid(),
        'expires_at' => now()->addMinutes(5),
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create incident ticket
    $ticketId = DB::table('incident_tickets')->insertGetId([
        'order_id' => $orderId,
        'payment_intent_id' => $intentId,
        'type' => 'paid_not_settled',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Act as manager and resolve the ticket via Livewire component
    $this->actingAs(
        \App\Models\User::find(1)
    );

    Livewire::test(\App\Livewire\Manager\ReconciliationQueue::class)
        ->call('openResolve', $ticketId)
        ->set('reasonCode', 'cash_verified')
        ->call('resolveTicket');

    // Verify the order is now settled
    $updatedOrder = DB::table('orders')->find($orderId);
    expect($updatedOrder->status)->toBe('settled');
    expect($updatedOrder->settled_by)->toBe(1);

    // Verify the ticket is resolved
    $updatedTicket = DB::table('incident_tickets')->find($ticketId);
    expect($updatedTicket->status)->toBe('resolved');
    expect($updatedTicket->resolution_reason_code)->toBe('cash_verified');
});

test('resolving reconciliation ticket fails when no confirmed payment exists', function () {
    // Create order and move to served
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'recon-fail', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);
    $orderId = (int) $order['id'];

    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transitionUseCase->execute($orderId, 'served', 2, 'kitchen', 2);

    // Create ticket WITHOUT a confirmed payment intent
    $ticketId = DB::table('incident_tickets')->insertGetId([
        'order_id' => $orderId,
        'type' => 'paid_not_settled',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(
        \App\Models\User::find(1)
    );

    Livewire::test(\App\Livewire\Manager\ReconciliationQueue::class)
        ->call('openResolve', $ticketId)
        ->set('reasonCode', 'cash_verified')
        ->call('resolveTicket')
        ->assertSet('error', 'Cannot resolve: no confirmed payment intent exists for this order.');

    // Order should remain served
    $updatedOrder = DB::table('orders')->find($orderId);
    expect($updatedOrder->status)->toBe('served');

    // Ticket should remain open
    $updatedTicket = DB::table('incident_tickets')->find($ticketId);
    expect($updatedTicket->status)->toBe('open');
});
