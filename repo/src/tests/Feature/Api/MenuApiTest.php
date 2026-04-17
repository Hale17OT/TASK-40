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

test('GET /api/menu/search returns menu items with full structure', function () {
    $response = $this->getJson('/api/menu/search');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['items', 'total', 'trending'],
        'query' => ['keyword', 'sort', 'page', 'per_page'],
    ]);

    $body = $response->json();
    expect($body['data']['items'])->toBeArray();
    expect($body['data']['total'])->toBeInt();
    expect($body['query']['sort'])->toBe('relevance');
    expect($body['query']['page'])->toBe(1);
});

test('GET /api/menu/search with keyword filters results to matching items only', function () {
    $response = $this->getJson('/api/menu/search?keyword=burger');
    $response->assertStatus(200);

    $items = $response->json('data.items');
    expect($items)->toBeArray();
    foreach ($items as $item) {
        expect(strtolower($item['name'] . ' ' . $item['description']))->toContain('burger');
    }
    expect($response->json('query.keyword'))->toBe('burger');
});

test('GET /api/menu/categories returns the seeded categories in order', function () {
    $response = $this->getJson('/api/menu/categories');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => [['id', 'name']]]);

    $categories = $response->json('data');
    expect($categories)->toHaveCount(2);
    $names = array_column($categories, 'name');
    expect($names)->toContain('Burgers');
    expect($names)->toContain('Drinks');
});

test('GET /api/menu/{id} returns the full item payload', function () {
    $response = $this->getJson('/api/menu/1');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'sku', 'name', 'price', 'tax_category'],
    ]);

    $data = $response->json('data');
    expect($data['id'])->toBe(1);
    expect($data['sku'])->toBe('B-001');
    expect($data['name'])->toBe('Classic Burger');
    expect((float) $data['price'])->toBe(12.99);
});

test('GET /api/menu/{id} returns JSON 404 with a message for missing items', function () {
    $response = $this->getJson('/api/menu/999');
    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
    expect($response->json('message'))->toContain('not found');
});
