<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

test('reconcile command detects paid-but-not-settled orders', function () {
    // Create order and move to served
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'recon', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);
    $orderId = (int) $order['id'];

    // Transition to served
    $transitionUseCase = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transitionUseCase->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transitionUseCase->execute($orderId, 'served', 2, 'kitchen', 2);

    // Create a confirmed payment intent without settling order
    $intentId = DB::table('payment_intents')->insertGetId([
        'order_id' => $orderId,
        'reference' => \Illuminate\Support\Str::uuid(),
        'amount' => 12.99,
        'hmac_signature' => 'test',
        'nonce' => 'test-nonce-recon',
        'expires_at' => now()->addMinutes(5),
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Run reconciliation
    $this->artisan('harborbite:reconcile-payments')->assertExitCode(0);

    $ticket = DB::table('incident_tickets')
        ->where('order_id', $orderId)
        ->where('type', 'paid_not_settled')
        ->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe('open');
});

test('reconcile command expires pending intents', function () {
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'recon2', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);

    // Create an expired pending intent
    DB::table('payment_intents')->insert([
        'order_id' => $order['id'],
        'reference' => \Illuminate\Support\Str::uuid(),
        'amount' => 12.99,
        'hmac_signature' => 'test',
        'nonce' => 'test-nonce-expired',
        'expires_at' => now()->subMinutes(10),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('harborbite:reconcile-payments')->assertExitCode(0);

    $intent = DB::table('payment_intents')->where('nonce', 'test-nonce-expired')->first();
    expect($intent->status)->toBe('failed');
});

test('incident ticket can be resolved with reason code', function () {
    // Create a ticket
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'ticket', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);

    $ticketId = DB::table('incident_tickets')->insertGetId([
        'order_id' => $order['id'],
        'type' => 'paid_not_settled',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Resolve the ticket
    DB::table('incident_tickets')->where('id', $ticketId)->update([
        'status' => 'resolved',
        'resolution_reason_code' => 'cash_verified',
        'receipt_reference' => encrypt('RECEIPT-12345'),
        'resolved_by' => 1,
        'resolved_at' => now(),
        'updated_at' => now(),
    ]);

    $resolved = DB::table('incident_tickets')->find($ticketId);
    expect($resolved->status)->toBe('resolved');
    expect($resolved->resolution_reason_code)->toBe('cash_verified');
});
