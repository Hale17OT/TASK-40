<?php

use App\Domain\Cart\TaxCalculator;

test('calculates item tax with matching rule', function () {
    $calc = new TaxCalculator([
        ['category' => 'hot_prepared', 'rate' => 0.0825],
    ]);
    $tax = $calc->calculateItemTax(10.00, 2, 'hot_prepared');
    expect($tax)->toBe(1.65); // 10 * 2 * 0.0825
});

test('uses default rate when no rule matches', function () {
    $calc = new TaxCalculator([], 0.10);
    $tax = $calc->calculateItemTax(10.00, 1, 'unknown_category');
    expect($tax)->toBe(1.00);
});

test('calculates cart tax for mixed items', function () {
    $calc = new TaxCalculator([
        ['category' => 'hot_prepared', 'rate' => 0.0825],
        ['category' => 'cold_prepared', 'rate' => 0.0625],
    ]);

    $items = [
        ['price' => 12.99, 'quantity' => 1, 'tax_category' => 'hot_prepared'],
        ['price' => 9.99, 'quantity' => 2, 'tax_category' => 'cold_prepared'],
    ];

    $tax = $calc->calculateCartTax($items);
    $expected = round(12.99 * 1 * 0.0825 + 9.99 * 2 * 0.0625, 2);
    expect($tax)->toBe($expected);
});

test('empty cart has zero tax', function () {
    $calc = new TaxCalculator();
    expect($calc->calculateCartTax([]))->toBe(0.0);
});

test('getBreakdown returns per-item info', function () {
    $calc = new TaxCalculator([
        ['category' => 'hot_prepared', 'rate' => 0.0825],
    ]);

    $items = [
        ['name' => 'Burger', 'price' => 10.00, 'quantity' => 1, 'tax_category' => 'hot_prepared'],
    ];

    $breakdown = $calc->getBreakdown($items);
    expect($breakdown)->toHaveCount(1);
    expect($breakdown[0]['name'])->toBe('Burger');
    expect($breakdown[0]['rate'])->toBe(0.0825);
    expect($breakdown[0]['tax_amount'])->toBe(0.83); // 10 * 1 * 0.0825 = 0.825 rounded
});

test('getRateForCategory returns correct rate', function () {
    $calc = new TaxCalculator([
        ['category' => 'beverage', 'rate' => 0.0825],
        ['category' => 'packaged', 'rate' => 0.04],
    ]);
    expect($calc->getRateForCategory('beverage'))->toBe(0.0825);
    expect($calc->getRateForCategory('packaged'))->toBe(0.04);
});

test('getRateForCategory returns default for unknown', function () {
    $calc = new TaxCalculator([], 0.05);
    expect($calc->getRateForCategory('anything'))->toBe(0.05);
});
