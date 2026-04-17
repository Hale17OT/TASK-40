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

test('updating a cart item from a foreign cart returns 200 but does NOT modify the foreign row', function () {
    // Create own cart first
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    // Try to update a foreign cart item (id=500 belongs to cart 99)
    $response = $this->patchJson('/api/cart/items/500', ['quantity' => 10]);
    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);

    // Verify the foreign cart item was NOT modified
    $foreignItem = DB::table('cart_items')->where('id', 500)->first();
    expect($foreignItem)->not->toBeNull();
    expect((int) $foreignItem->quantity)->toBe(3);
    expect((float) $foreignItem->unit_price_snapshot)->toBe(12.99);
    expect((int) $foreignItem->cart_id)->toBe(99); // still bound to foreign cart

    // Own cart items should be untouched
    $ownCart = DB::table('carts')->where('session_id', session()->getId())->first();
    $ownItems = DB::table('cart_items')->where('cart_id', $ownCart->id)->get();
    expect($ownItems)->toHaveCount(1);
    expect((int) $ownItems->first()->quantity)->toBe(1); // unchanged by the foreign attempt
});

test('deleting a cart item from a foreign cart returns 200 but does NOT delete the foreign row', function () {
    // Create own cart first
    $this->postJson('/api/cart/items', ['menu_item_id' => 1])->assertStatus(201);

    // Try to delete a foreign cart item (id=500 belongs to cart 99)
    $response = $this->deleteJson('/api/cart/items/500');
    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);

    // Verify the foreign cart item still exists with original values
    $foreignItem = DB::table('cart_items')->where('id', 500)->first();
    expect($foreignItem)->not->toBeNull();
    expect((int) $foreignItem->cart_id)->toBe(99);
    expect((int) $foreignItem->quantity)->toBe(3);
});
