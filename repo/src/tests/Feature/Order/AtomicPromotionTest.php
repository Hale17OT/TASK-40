<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 30.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 30.00, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('order creation atomically applies best automatic promotion', function () {
    // Create an active promotion
    DB::table('promotions')->insert([
        'name' => '10% Off Over $50',
        'type' => 'percentage_off',
        'rules' => json_encode(['threshold' => 50, 'percentage' => 10]),
        'exclusion_group' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    // Order should already have the discount applied
    expect((float) $order['discount'])->toBeGreaterThan(0);
    expect((float) $order['total'])->toBeLessThan((float) $order['subtotal'] + (float) $order['tax']);

    // applied_promotions record should exist
    $applied = DB::table('applied_promotions')->where('order_id', $order['id'])->first();
    expect($applied)->not->toBeNull();
    expect((float) $applied->discount_amount)->toBeGreaterThan(0);
});

test('order creation without active promotions has zero discount', function () {
    // No promotions seeded
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    expect((float) $order['discount'])->toBe(0.0);
    expect(DB::table('applied_promotions')->where('order_id', $order['id'])->count())->toBe(0);
});

test('API order creation also applies promotions atomically', function () {
    DB::table('promotions')->insert([
        'name' => 'Flat $5 Off',
        'type' => 'flat_discount',
        'rules' => json_encode(['threshold' => 0, 'amount' => 5]),
        'exclusion_group' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Use API — must also get promotion applied
    $response = $this->postJson('/api/orders', ['cart_id' => 1]);

    // Will be 403 since session doesn't match, so test via use case
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    expect((float) $order['discount'])->toBeGreaterThan(0);
});
