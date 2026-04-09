<?php

use App\Domain\Search\AllergenFilter;

test('builds exclusion for nuts', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['nuts']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('contains_nuts');
    expect($result[0]['exclude_when'])->toBeTrue();
});

test('builds exclusion for gluten', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['gluten']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('gluten_free');
    expect($result[0]['exclude_when'])->toBeFalse();
});

test('builds exclusion for dairy', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['dairy']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('contains_dairy');
    expect($result[0]['exclude_when'])->toBeTrue();
});

test('builds exclusion for shellfish', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['shellfish']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('contains_shellfish');
    expect($result[0]['exclude_when'])->toBeTrue();
});

test('builds exclusion for vegan', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['vegan']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('is_vegan');
    expect($result[0]['exclude_when'])->toBeFalse();
});

test('builds numeric exclusion for spicy', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['spicy']);

    expect($result)->toHaveCount(1);
    expect($result[0]['key'])->toBe('spicy_level');
    expect($result[0]['exclude_when_gt'])->toBe(0);
});

test('builds multiple exclusions', function () {
    $filter = new AllergenFilter();
    $result = $filter->buildExclusions(['nuts', 'gluten', 'dairy', 'spicy']);

    expect($result)->toHaveCount(4);
});

test('availableFilters returns all filter options', function () {
    $filter = new AllergenFilter();
    $filters = $filter->availableFilters();

    expect($filters)->toHaveKey('nuts');
    expect($filters)->toHaveKey('gluten');
    expect($filters)->toHaveKey('dairy');
    expect($filters)->toHaveKey('shellfish');
    expect($filters)->toHaveKey('vegan');
    expect($filters)->toHaveKey('spicy');
});
