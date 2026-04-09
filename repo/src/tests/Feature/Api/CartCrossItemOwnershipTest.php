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

    // Create a foreign cart belonging to a different session
    DB::table('carts')->insert([
        'id' => 99, 'session_id' => 'foreign-session', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('cart_items')->insert([
        'id' => 500, 'cart_id' => 99, 'menu_item_id' => 1, 'quantity' => 3, 'unit_price_snapshot' => 12.99,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('updating a cart item from a foreign cart has no effect', function () {
    // Create own cart first
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    // Try to update a foreign cart item (id=500 belongs to cart 99)
    $this->patchJson('/api/cart/items/500', ['quantity' => 10])
        ->assertStatus(200);

    // Verify the foreign cart item was NOT modified
    $foreignItem = DB::table('cart_items')->where('id', 500)->first();
    expect($foreignItem->quantity)->toBe(3);
});

test('deleting a cart item from a foreign cart has no effect', function () {
    // Create own cart first
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    // Try to delete a foreign cart item (id=500 belongs to cart 99)
    $this->deleteJson('/api/cart/items/500')
        ->assertStatus(200);

    // Verify the foreign cart item still exists
    $foreignItem = DB::table('cart_items')->where('id', 500)->first();
    expect($foreignItem)->not->toBeNull();
});
