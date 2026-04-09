<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Seed users
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Menu
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Sides', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'BRG-001', 'menu_category_id' => 1, 'name' => 'Classic Burger', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'BRG-002', 'menu_category_id' => 1, 'name' => 'Spicy Burger', 'price' => 14.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'sku' => 'SDE-001', 'menu_category_id' => 2, 'name' => 'Fries', 'price' => 4.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Active promotions
    DB::table('promotions')->insert([
        [
            'id' => 1, 'name' => '10% off over $30', 'type' => 'percentage_off',
            'rules' => json_encode(['threshold' => 30, 'percentage' => 10]),
            'exclusion_group' => 'cart_discount',
            'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-12-31 23:59:59',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'id' => 2, 'name' => 'BOGO Burgers', 'type' => 'bogo',
            'rules' => json_encode(['target_skus' => ['BRG-001', 'BRG-002']]),
            'exclusion_group' => 'item_discount',
            'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-12-31 23:59:59',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);
});

test('promotion evaluator finds best offer for cart with burgers', function () {
    $evaluator = app(\App\Domain\Promotion\PromotionEvaluator::class);

    $cartItems = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 2, 'name' => 'Classic Burger'],
        ['sku' => 'SDE-001', 'price' => 4.99, 'quantity' => 1, 'name' => 'Fries'],
    ];
    $subtotal = 30.97;

    $promos = DB::table('promotions')
        ->where('is_active', true)
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->get()
        ->map(function ($p) {
            $arr = (array) $p;
            $arr['rules'] = json_decode($arr['rules'], true);
            return $arr;
        })
        ->toArray();

    $result = $evaluator->evaluate($cartItems, $promos, $subtotal);

    // BOGO saves $12.99 vs 10% saves ~$3.10 — BOGO wins
    expect($result)->not->toBeNull();
    expect($result['promotion_id'])->toBe(2);
    expect($result['discount_amount'])->toBe(12.99);
});

test('expired promotion is not loaded', function () {
    // Insert expired promo
    DB::table('promotions')->insert([
        'id' => 99, 'name' => 'Expired Promo', 'type' => 'flat_discount',
        'rules' => json_encode(['threshold' => 10, 'amount' => 100]),
        'exclusion_group' => null,
        'starts_at' => '2025-01-01 00:00:00', 'ends_at' => '2025-12-31 23:59:59',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $promos = DB::table('promotions')
        ->where('is_active', true)
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->get();

    $ids = $promos->pluck('id')->toArray();
    expect($ids)->not->toContain(99);
});

test('inactive promotion is not loaded', function () {
    DB::table('promotions')->insert([
        'id' => 98, 'name' => 'Inactive', 'type' => 'flat_discount',
        'rules' => json_encode(['threshold' => 10, 'amount' => 100]),
        'exclusion_group' => null,
        'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-12-31 23:59:59',
        'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $promos = DB::table('promotions')
        ->where('is_active', true)
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->get();

    $ids = $promos->pluck('id')->toArray();
    expect($ids)->not->toContain(98);
});
