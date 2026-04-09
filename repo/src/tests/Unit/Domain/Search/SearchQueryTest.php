<?php

use App\Domain\Search\SearchQuery;

test('validates price range min greater than max', function () {
    $q = new SearchQuery(priceMin: 20.0, priceMax: 10.0);
    $errors = $q->validate();
    expect($errors)->toContain('Minimum price cannot be greater than maximum price.');
});

test('validates negative min price', function () {
    $q = new SearchQuery(priceMin: -5.0);
    $errors = $q->validate();
    expect($errors)->toContain('Minimum price cannot be negative.');
});

test('validates invalid sort option', function () {
    $q = new SearchQuery(sort: 'invalid');
    $errors = $q->validate();
    expect($errors)->toContain('Invalid sort option.');
});

test('valid query has no errors', function () {
    $q = new SearchQuery(keyword: 'burger', priceMin: 5.0, priceMax: 20.0, sort: 'price_asc');
    expect($q->validate())->toBeEmpty();
});

test('hasKeyword returns true for non-empty keyword', function () {
    expect((new SearchQuery(keyword: 'burger'))->hasKeyword())->toBeTrue();
    expect((new SearchQuery(keyword: ''))->hasKeyword())->toBeFalse();
    expect((new SearchQuery(keyword: '  '))->hasKeyword())->toBeFalse();
});

test('hasPriceRange detects range', function () {
    expect((new SearchQuery(priceMin: 5.0))->hasPriceRange())->toBeTrue();
    expect((new SearchQuery(priceMax: 20.0))->hasPriceRange())->toBeTrue();
    expect((new SearchQuery())->hasPriceRange())->toBeFalse();
});

test('hasAllergenExclusions detects exclusions', function () {
    expect((new SearchQuery(allergenExclusions: ['nuts']))->hasAllergenExclusions())->toBeTrue();
    expect((new SearchQuery())->hasAllergenExclusions())->toBeFalse();
});
