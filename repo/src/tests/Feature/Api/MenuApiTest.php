<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Drinks', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'A classic burger', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{"allergens":["gluten"]}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'D-001', 'menu_category_id' => 2, 'name' => 'Cola', 'description' => 'Refreshing cola', 'price' => 2.99, 'tax_category' => 'cold_beverage', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('GET /api/menu/search returns menu items', function () {
    $response = $this->getJson('/api/menu/search');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data', 'query']);
});

test('GET /api/menu/search with keyword filters results', function () {
    $response = $this->getJson('/api/menu/search?keyword=burger');
    $response->assertStatus(200);
});

test('GET /api/menu/categories returns categories', function () {
    $response = $this->getJson('/api/menu/categories');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

test('GET /api/menu/{id} returns single item', function () {
    $response = $this->getJson('/api/menu/1');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

test('GET /api/menu/{id} returns 404 for non-existent item', function () {
    $response = $this->getJson('/api/menu/999');
    $response->assertStatus(404);
});
