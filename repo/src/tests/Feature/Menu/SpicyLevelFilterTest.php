<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Mains', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'M-001', 'menu_category_id' => 1, 'name' => 'Mild Wings', 'description' => 'Mild', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['spicy_level' => 1]), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'M-002', 'menu_category_id' => 1, 'name' => 'Hot Wings', 'description' => 'Hot', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['spicy_level' => 3]), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'sku' => 'M-003', 'menu_category_id' => 1, 'name' => 'Plain Salad', 'description' => 'Not spicy', 'price' => 8.00, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['spicy_level' => 0]), 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('API search with max_spicy_level=0 excludes all spicy items', function () {
    $response = $this->getJson('/api/menu/search?max_spicy_level=0');
    $response->assertStatus(200);

    $items = collect($response->json('data.items'));
    // Only Plain Salad (spicy_level=0) should remain
    expect($items->pluck('name')->toArray())->toContain('Plain Salad');
    expect($items->pluck('name')->toArray())->not->toContain('Mild Wings');
    expect($items->pluck('name')->toArray())->not->toContain('Hot Wings');
});

test('API search with max_spicy_level=1 includes mild but excludes hot', function () {
    $response = $this->getJson('/api/menu/search?max_spicy_level=1');
    $response->assertStatus(200);

    $items = collect($response->json('data.items'));
    expect($items->pluck('name')->toArray())->toContain('Plain Salad');
    expect($items->pluck('name')->toArray())->toContain('Mild Wings');
    expect($items->pluck('name')->toArray())->not->toContain('Hot Wings');
});

test('API search without max_spicy_level returns all items', function () {
    $response = $this->getJson('/api/menu/search');
    $response->assertStatus(200);

    $items = collect($response->json('data.items'));
    expect($items)->toHaveCount(3);
});

test('AllergenFilter builds correct spicy level exclusion', function () {
    $filter = new \App\Domain\Search\AllergenFilter();

    $exclusion = $filter->buildSpicyLevelExclusion(2);
    expect($exclusion['key'])->toBe('spicy_level');
    expect($exclusion['exclude_when_gt'])->toBe(2);
});

test('AllergenFilter provides spicy level options', function () {
    $filter = new \App\Domain\Search\AllergenFilter();
    $options = $filter->spicyLevelOptions();

    expect($options)->toHaveCount(4);
    expect($options[0])->toBe('Not Spicy');
    expect($options[1])->toBe('Mild');
    expect($options[2])->toBe('Medium');
    expect($options[3])->toBe('Hot');
});
