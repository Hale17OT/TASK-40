<?php

use App\Application\Search\Ports\MenuRepositoryInterface;
use App\Domain\Search\SearchQuery;
use App\Infrastructure\Persistence\Repositories\EloquentMenuRepository;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Drinks', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Disabled', 'sort_order' => 3, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'Beef', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'D-001', 'menu_category_id' => 2, 'name' => 'Cola', 'description' => 'Soda', 'price' => 2.99, 'tax_category' => 'cold_beverage', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'sku' => 'B-002', 'menu_category_id' => 1, 'name' => 'Hidden Burger', 'description' => 'Inactive', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => false, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('EloquentMenuRepository implements the MenuRepositoryInterface port', function () {
    $repo = new EloquentMenuRepository();
    expect($repo)->toBeInstanceOf(MenuRepositoryInterface::class);
});

test('getCategories returns only active categories in sort_order', function () {
    $repo = new EloquentMenuRepository();
    $categories = $repo->getCategories();

    expect($categories)->toHaveCount(2);
    $names = array_column($categories, 'name');
    expect($names)->toContain('Burgers');
    expect($names)->toContain('Drinks');
    expect($names)->not->toContain('Disabled');
});

test('findById returns a full item array for an active item', function () {
    $repo = new EloquentMenuRepository();
    $item = $repo->findById(1);

    expect($item)->not->toBeNull();
    expect($item['sku'])->toBe('B-001');
    expect($item['name'])->toBe('Classic Burger');
    expect((float) $item['price'])->toBe(12.99);
});

test('findById returns null for a missing item', function () {
    $repo = new EloquentMenuRepository();
    expect($repo->findById(99999))->toBeNull();
});

test('search filters inactive items from results', function () {
    $repo = new EloquentMenuRepository();
    $result = $repo->search(new SearchQuery());

    $names = array_column($result['items'], 'name');
    expect($names)->not->toContain('Hidden Burger');
});

test('search honors category filter', function () {
    $repo = new EloquentMenuRepository();
    $result = $repo->search(new SearchQuery(categoryId: 2));

    expect($result['items'])->not->toBeEmpty();
    foreach ($result['items'] as $item) {
        expect((int) $item['category_id'])->toBe(2);
    }
});

test('search honors price range', function () {
    $repo = new EloquentMenuRepository();
    $result = $repo->search(new SearchQuery(priceMin: 10.0, priceMax: 20.0));

    foreach ($result['items'] as $item) {
        expect((float) $item['price'])->toBeGreaterThanOrEqual(10.0);
        expect((float) $item['price'])->toBeLessThanOrEqual(20.0);
    }
});
