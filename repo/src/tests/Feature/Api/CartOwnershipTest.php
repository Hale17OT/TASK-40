<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('cart operations are bound to server session, not caller-controlled header', function () {
    // Add item (creates cart bound to test session)
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    // Cart should be visible to this session
    $response = $this->getJson('/api/cart');
    $response->assertStatus(200);
    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
});

test('order creation rejects foreign cart_id not owned by session', function () {
    // Create cart belonging to a different session
    DB::table('carts')->insert([
        'id' => 99,
        'session_id' => 'other-session-id-that-doesnt-match',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('cart_items')->insert([
        'cart_id' => 99,
        'menu_item_id' => 1,
        'quantity' => 1,
        'unit_price_snapshot' => 12.99,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Try to create order from another session's cart
    $response = $this->postJson('/api/orders', ['cart_id' => 99]);
    $response->assertStatus(403);
    $response->assertJsonFragment(['message' => 'Cart not found or access denied.']);
});

test('order creation succeeds for own session cart', function () {
    // Add item to own session cart
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    $cart = DB::table('carts')->where('session_id', session()->getId())->first();

    $response = $this->postJson('/api/orders', ['cart_id' => $cart->id]);
    $response->assertStatus(201);
});
