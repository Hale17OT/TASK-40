<?php

use App\Domain\Risk\RateLimitEvaluator;

test('under threshold is not exceeded', function () {
    $eval = new RateLimitEvaluator();
    expect($eval->isExceeded(5, 10))->toBeFalse();
});

test('at threshold is exceeded', function () {
    $eval = new RateLimitEvaluator();
    expect($eval->isExceeded(10, 10))->toBeTrue();
});

test('over threshold is exceeded', function () {
    $eval = new RateLimitEvaluator();
    expect($eval->isExceeded(15, 10))->toBeTrue();
});

test('zero count is not exceeded', function () {
    $eval = new RateLimitEvaluator();
    expect($eval->isExceeded(0, 10))->toBeFalse();
});

test('build key produces expected format', function () {
    $eval = new RateLimitEvaluator();
    $key = $eval->buildKey('device', 'abc123', 'checkout');
    expect($key)->toBe('rate_limit:device:checkout:abc123');
});
