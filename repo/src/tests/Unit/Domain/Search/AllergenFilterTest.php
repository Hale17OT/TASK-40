<?php

use App\Domain\Search\AllergenFilter;

test('builds nut exclusion', function () {
    $filter = new AllergenFilter();
    $exclusions = $filter->buildExclusions(['nuts']);
    expect($exclusions)->toHaveCount(1);
    expect($exclusions[0]['key'])->toBe('contains_nuts');
    expect($exclusions[0]['exclude_when'])->toBeTrue();
});

test('builds gluten exclusion', function () {
    $filter = new AllergenFilter();
    $exclusions = $filter->buildExclusions(['gluten']);
    expect($exclusions)->toHaveCount(1);
    expect($exclusions[0]['key'])->toBe('gluten_free');
    expect($exclusions[0]['exclude_when'])->toBeFalse();
});

test('builds multiple exclusions', function () {
    $filter = new AllergenFilter();
    $exclusions = $filter->buildExclusions(['nuts', 'gluten']);
    expect($exclusions)->toHaveCount(2);
});

test('handles empty allergens', function () {
    $filter = new AllergenFilter();
    expect($filter->buildExclusions([]))->toBeEmpty();
});

test('handles unknown allergen gracefully', function () {
    $filter = new AllergenFilter();
    $exclusions = $filter->buildExclusions(['unknown_allergen']);
    expect($exclusions)->toBeEmpty();
});

test('available filters lists options', function () {
    $filter = new AllergenFilter();
    $available = $filter->availableFilters();
    expect($available)->toHaveKey('nuts');
    expect($available)->toHaveKey('gluten');
});
