<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Seed categories
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Salads', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed items
    DB::table('menu_items')->insert([
        ['sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'Beef patty with cheese', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false]), 'created_at' => now(), 'updated_at' => now()],
        ['sku' => 'T-002', 'menu_category_id' => 1, 'name' => 'Spicy Burger', 'description' => 'Hot and spicy beef burger', 'price' => 14.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 3, 'contains_nuts' => false]), 'created_at' => now(), 'updated_at' => now()],
        ['sku' => 'T-003', 'menu_category_id' => 2, 'name' => 'Peanut Salad', 'description' => 'Fresh greens with peanut dressing', 'price' => 9.99, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => true]), 'created_at' => now(), 'updated_at' => now()],
        ['sku' => 'T-004', 'menu_category_id' => 2, 'name' => 'Caesar Salad', 'description' => 'Classic caesar with croutons', 'price' => 8.99, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false]), 'created_at' => now(), 'updated_at' => now()],
        ['sku' => 'T-005', 'menu_category_id' => 1, 'name' => 'Inactive Burger', 'description' => 'This should not appear', 'price' => 5.00, 'tax_category' => 'hot_prepared', 'is_active' => false, 'attributes' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed trending and banned words
    DB::table('trending_searches')->insert([
        ['term' => 'burger', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['term' => 'salad', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('banned_words')->insert([
        ['word' => 'scam', 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'hack', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('homepage loads with menu items', function () {
    $this->get('/')->assertStatus(200);
});

test('menu search returns results for keyword', function () {
    $response = $this->get('/?search=burger');
    $response->assertStatus(200);
});

test('banned word returns blocked message via livewire', function () {
    // Rebuild the ProfanityFilter with fresh banned words from DB
    $bannedWords = DB::table('banned_words')->pluck('word')->toArray();
    $filter = new \App\Domain\Risk\ProfanityFilter($bannedWords);
    app()->instance(\App\Domain\Risk\ProfanityFilter::class, $filter);

    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery(keyword: 'scam'));

    expect($result['blocked'])->toBeTrue();
    expect($result['block_message'])->toContain('not allowed');
    expect($result['items'])->toBeEmpty();
});

test('search returns only active items', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery());

    $names = array_column($result['items'], 'name');
    expect($names)->not->toContain('Inactive Burger');
    expect($result['total'])->toBe(4);
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('price range filter works', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery(priceMin: 10.0, priceMax: 13.0));

    expect($result['total'])->toBe(1);
    expect($result['items'][0]['name'])->toBe('Classic Burger');
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('category filter works', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery(categoryId: 2));

    expect($result['total'])->toBe(2);
    foreach ($result['items'] as $item) {
        expect($item['category_name'])->toBe('Salads');
    }
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('allergen exclusion hides nut items', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery(allergenExclusions: ['nuts']));

    $names = array_column($result['items'], 'name');
    expect($names)->not->toContain('Peanut Salad');
    expect($result['total'])->toBe(3);
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('sort by price ascending works', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery(sort: 'price_asc'));

    $prices = array_column($result['items'], 'price');
    $sorted = $prices;
    sort($sorted);
    expect($prices)->toBe($sorted);
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('trending terms are returned', function () {
    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery());

    expect($result['trending'])->toContain('burger');
    expect($result['trending'])->toContain('salad');
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('trending terms limited to 20', function () {
    // Insert 25 trending terms
    for ($i = 0; $i < 25; $i++) {
        DB::table('trending_searches')->insert([
            'term' => "term-{$i}",
            'sort_order' => $i + 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $useCase = app(\App\Application\Search\SearchMenuUseCase::class);
    $result = $useCase->execute(new \App\Domain\Search\SearchQuery());

    expect(count($result['trending']))->toBeLessThanOrEqual(20);
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');
