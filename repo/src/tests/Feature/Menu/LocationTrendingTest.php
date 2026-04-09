<?php

use App\Application\Search\SearchMenuUseCase;
use App\Domain\Search\SearchQuery;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_items')->insert([
        ['sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'description' => 'Beef patty', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Global trending terms (no location)
    DB::table('trending_searches')->insert([
        ['term' => 'global-trend-1', 'sort_order' => 1, 'location_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['term' => 'global-trend-2', 'sort_order' => 2, 'location_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Location 10 trending terms
    DB::table('trending_searches')->insert([
        ['term' => 'loc10-burger', 'sort_order' => 1, 'location_id' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['term' => 'loc10-fries', 'sort_order' => 2, 'location_id' => 10, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Location 20 trending terms
    DB::table('trending_searches')->insert([
        ['term' => 'loc20-salad', 'sort_order' => 1, 'location_id' => 20, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('search with locationId returns only that location trending terms', function () {
    $useCase = app(SearchMenuUseCase::class);

    $result = $useCase->execute(new SearchQuery(locationId: 10));

    expect($result['trending'])->toContain('loc10-burger');
    expect($result['trending'])->toContain('loc10-fries');
    expect($result['trending'])->not->toContain('global-trend-1');
    expect($result['trending'])->not->toContain('loc20-salad');
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('search with different locationId returns different trending terms', function () {
    $useCase = app(SearchMenuUseCase::class);

    $result = $useCase->execute(new SearchQuery(locationId: 20));

    expect($result['trending'])->toContain('loc20-salad');
    expect($result['trending'])->not->toContain('loc10-burger');
    expect($result['trending'])->not->toContain('global-trend-1');
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('search without locationId returns all trending terms', function () {
    $useCase = app(SearchMenuUseCase::class);

    $result = $useCase->execute(new SearchQuery());

    // Without locationId filter, all terms are returned (up to limit of 20)
    expect($result['trending'])->toContain('global-trend-1');
    expect($result['trending'])->toContain('loc10-burger');
    expect($result['trending'])->toContain('loc20-salad');
})->skip(fn () => config('database.default') === 'sqlite', 'Requires PostgreSQL');

test('blocked search returns location-scoped trending suggestions', function () {
    DB::table('banned_words')->insert([
        ['word' => 'scam', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $bannedWords = DB::table('banned_words')->pluck('word')->toArray();
    $filter = new \App\Domain\Risk\ProfanityFilter($bannedWords);
    app()->instance(\App\Domain\Risk\ProfanityFilter::class, $filter);

    $useCase = app(SearchMenuUseCase::class);
    $result = $useCase->execute(new SearchQuery(keyword: 'scam', locationId: 10));

    expect($result['blocked'])->toBeTrue();
    expect($result['trending'])->toContain('loc10-burger');
    expect($result['trending'])->not->toContain('global-trend-1');
    expect($result['trending'])->not->toContain('loc20-salad');
});

test('validation error returns location-scoped trending terms', function () {
    $useCase = app(SearchMenuUseCase::class);

    // priceMin > priceMax triggers validation error
    $result = $useCase->execute(new SearchQuery(priceMin: 100.0, priceMax: 1.0, locationId: 10));

    expect($result['errors'])->not->toBeEmpty();
    expect($result['trending'])->toContain('loc10-burger');
    expect($result['trending'])->not->toContain('global-trend-1');
});
