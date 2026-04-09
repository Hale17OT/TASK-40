<?php

use App\Domain\Promotion\PromotionEvaluator;

beforeEach(function () {
    $this->evaluator = new PromotionEvaluator();
});

test('returns null for empty cart', function () {
    $promos = [['id' => 1, 'name' => 'Test', 'type' => 'percentage_off', 'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => null]];
    expect($this->evaluator->evaluate([], $promos, 0))->toBeNull();
});

test('returns null for no promotions', function () {
    $items = [['sku' => 'A', 'price' => 15.00, 'quantity' => 2, 'name' => 'Item A']];
    expect($this->evaluator->evaluate($items, [], 30.00))->toBeNull();
});

test('percentage off applied when threshold met', function () {
    $items = [['sku' => 'A', 'price' => 15.00, 'quantity' => 3, 'name' => 'Item A']];
    $promos = [[
        'id' => 1, 'name' => '10% off over $30', 'type' => 'percentage_off',
        'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 45.00);
    expect($result)->not->toBeNull();
    expect($result['discount_amount'])->toBe(4.50); // 45 * 10%
    expect($result['promotion_id'])->toBe(1);
});

test('percentage off not applied when below threshold', function () {
    $items = [['sku' => 'A', 'price' => 10.00, 'quantity' => 2, 'name' => 'Item A']];
    $promos = [[
        'id' => 1, 'name' => '10% off over $30', 'type' => 'percentage_off',
        'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 20.00);
    expect($result)->toBeNull();
});

test('flat discount applied when threshold met', function () {
    $items = [['sku' => 'A', 'price' => 25.00, 'quantity' => 3, 'name' => 'Item A']];
    $promos = [[
        'id' => 2, 'name' => '$5 off over $50', 'type' => 'flat_discount',
        'rules' => ['threshold' => 50, 'amount' => 5.00], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 75.00);
    expect($result['discount_amount'])->toBe(5.00);
});

test('bogo applies for matching SKUs', function () {
    $items = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 2, 'name' => 'Burger'],
    ];
    $promos = [[
        'id' => 3, 'name' => 'BOGO Burgers', 'type' => 'bogo',
        'rules' => ['target_skus' => ['BRG-001', 'BRG-002']], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 25.98);
    expect($result)->not->toBeNull();
    expect($result['discount_amount'])->toBe(12.99); // Cheapest free
});

test('bogo with odd quantity gives correct discount', function () {
    // 3 items: 1 pair = 1 free, 1 leftover
    $items = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 3, 'name' => 'Burger'],
    ];
    $promos = [[
        'id' => 3, 'name' => 'BOGO', 'type' => 'bogo',
        'rules' => ['target_skus' => ['BRG-001']], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 38.97);
    expect($result['discount_amount'])->toBe(12.99); // 1 free item
});

test('bogo with different prices makes cheapest free', function () {
    $items = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 1, 'name' => 'Cheap Burger'],
        ['sku' => 'BRG-002', 'price' => 14.99, 'quantity' => 1, 'name' => 'Expensive Burger'],
    ];
    $promos = [[
        'id' => 3, 'name' => 'BOGO', 'type' => 'bogo',
        'rules' => ['target_skus' => ['BRG-001', 'BRG-002']], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 27.98);
    expect($result['discount_amount'])->toBe(12.99); // Cheapest is free
});

test('bogo needs at least 2 qualifying items', function () {
    $items = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 1, 'name' => 'Burger'],
    ];
    $promos = [[
        'id' => 3, 'name' => 'BOGO', 'type' => 'bogo',
        'rules' => ['target_skus' => ['BRG-001']], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 12.99);
    expect($result)->toBeNull();
});

test('percentage off second item', function () {
    $items = [
        ['sku' => 'SDE-001', 'price' => 4.99, 'quantity' => 2, 'name' => 'Fries'],
    ];
    $promos = [[
        'id' => 4, 'name' => '50% off second', 'type' => 'percentage_off_second',
        'rules' => ['target_skus' => ['SDE-001'], 'percentage' => 50], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 9.98);
    expect($result['discount_amount'])->toBe(2.50); // 4.99 * 50% = 2.495 rounded to 2.50
});

test('best offer wins across multiple promotions', function () {
    $items = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 2, 'name' => 'Burger'],
        ['sku' => 'SDE-001', 'price' => 4.99, 'quantity' => 1, 'name' => 'Fries'],
    ];
    $subtotal = 30.97;

    $promos = [
        [
            'id' => 1, 'name' => '10% off over $30', 'type' => 'percentage_off',
            'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => 'cart_discount',
        ],
        [
            'id' => 3, 'name' => 'BOGO Burgers', 'type' => 'bogo',
            'rules' => ['target_skus' => ['BRG-001']], 'exclusion_group' => 'item_discount',
        ],
    ];

    $result = $this->evaluator->evaluate($items, $promos, $subtotal);
    // BOGO saves $12.99, 10% saves $3.10 — BOGO wins
    expect($result['promotion_id'])->toBe(3);
    expect($result['discount_amount'])->toBe(12.99);
});

test('mutual exclusion within same group picks best', function () {
    $items = [['sku' => 'A', 'price' => 20.00, 'quantity' => 3, 'name' => 'Item']];
    $subtotal = 60.00;

    $promos = [
        [
            'id' => 1, 'name' => '10% off over $30', 'type' => 'percentage_off',
            'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => 'cart_discount',
        ],
        [
            'id' => 2, 'name' => '$5 off over $50', 'type' => 'flat_discount',
            'rules' => ['threshold' => 50, 'amount' => 5.00], 'exclusion_group' => 'cart_discount',
        ],
    ];

    $result = $this->evaluator->evaluate($items, $promos, $subtotal);
    // 10% of $60 = $6 > $5 flat, so promo 1 wins
    expect($result['promotion_id'])->toBe(1);
    expect($result['discount_amount'])->toBe(6.00);
});

test('non-matching sku returns null for bogo', function () {
    $items = [['sku' => 'NOMATCH', 'price' => 15.00, 'quantity' => 2, 'name' => 'Item']];
    $promos = [[
        'id' => 3, 'name' => 'BOGO', 'type' => 'bogo',
        'rules' => ['target_skus' => ['BRG-001']], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 30.00);
    expect($result)->toBeNull();
});

test('description contains savings amount', function () {
    $items = [['sku' => 'A', 'price' => 20.00, 'quantity' => 2, 'name' => 'Item']];
    $promos = [[
        'id' => 1, 'name' => '10% off', 'type' => 'percentage_off',
        'rules' => ['threshold' => 30, 'percentage' => 10], 'exclusion_group' => null,
    ]];

    $result = $this->evaluator->evaluate($items, $promos, 40.00);
    expect($result['description'])->toContain('$4.00');
});
