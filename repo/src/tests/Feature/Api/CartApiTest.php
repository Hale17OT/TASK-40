<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('POST /api/cart/items adds item to cart', function () {
    $response = $this->postJson('/api/cart/items', [
        'menu_item_id' => 1,
    ]);

    $response->assertStatus(201);
    expect(DB::table('cart_items')->count())->toBe(1);
});

test('POST /api/cart/items returns 404 for inactive item', function () {
    DB::table('menu_items')->where('id', 1)->update(['is_active' => false]);

    $response = $this->postJson('/api/cart/items', [
        'menu_item_id' => 1,
    ]);

    $response->assertStatus(404);
});

test('GET /api/cart returns empty cart initially', function () {
    $response = $this->getJson('/api/cart');
    $response->assertStatus(200);
});

test('PATCH /api/cart/items/{id} updates item note with encryption', function () {
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);

    $cartItem = DB::table('cart_items')->first();

    $response = $this->patchJson("/api/cart/items/{$cartItem->id}", [
        'note' => 'Extra ketchup please',
    ]);

    $response->assertStatus(200);

    $updated = DB::table('cart_items')->find($cartItem->id);
    // Note should be encrypted in DB
    expect($updated->note)->not->toBe('Extra ketchup please');
    // But should decrypt correctly
    expect(Crypt::decryptString($updated->note))->toBe('Extra ketchup please');
});

test('DELETE /api/cart/items/{id} removes item', function () {
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    $cartItem = DB::table('cart_items')->first();

    $response = $this->deleteJson("/api/cart/items/{$cartItem->id}");
    $response->assertStatus(200);
    expect(DB::table('cart_items')->count())->toBe(0);
});

test('DELETE /api/cart clears the cart', function () {
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    expect(DB::table('cart_items')->count())->toBe(1);

    $response = $this->deleteJson('/api/cart');
    $response->assertStatus(200);
    expect(DB::table('cart_items')->count())->toBe(0);
});
