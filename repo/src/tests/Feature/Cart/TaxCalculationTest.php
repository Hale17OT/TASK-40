<?php

use App\Application\Cart\CartService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Seed menu categories
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Hot Food', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cold Food', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed menu items with different tax categories
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'HOT-001', 'menu_category_id' => 1, 'name' => 'Grilled Burger', 'description' => 'Hot prepared burger', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'COLD-001', 'menu_category_id' => 2, 'name' => 'Caesar Salad', 'description' => 'Cold prepared salad', 'price' => 9.99, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'sku' => 'HOT-002', 'menu_category_id' => 1, 'name' => 'Fish & Chips', 'description' => 'Hot prepared fish', 'price' => 14.50, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed tax rules with different rates for hot and cold
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2025-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
        ['category' => 'cold_prepared', 'rate' => 0.0625, 'effective_from' => '2025-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('hot food item gets correct tax rate applied', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // Grilled Burger, hot_prepared @ 12.99

    $details = $service->loadCartDetails($sessionId);

    expect($details['items'])->not->toBeEmpty();

    $expectedTax = round(12.99 * 1 * 0.0825, 2); // 1.07
    expect($details['tax'])->toBe($expectedTax);
    expect($details['subtotal'])->toBe(12.99);
    expect($details['total'])->toBe(round(12.99 + $expectedTax, 2));
});

test('cold food item gets different tax rate applied', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 2); // Caesar Salad, cold_prepared @ 9.99

    $details = $service->loadCartDetails($sessionId);

    expect($details['items'])->not->toBeEmpty();

    $expectedTax = round(9.99 * 1 * 0.0625, 2); // 0.62
    expect($details['tax'])->toBe($expectedTax);
    expect($details['subtotal'])->toBe(9.99);
    expect($details['total'])->toBe(round(9.99 + $expectedTax, 2));
});

test('mixed cart calculates per-item tax correctly', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // Grilled Burger, hot_prepared @ 12.99
    $service->addItem($sessionId, 2); // Caesar Salad, cold_prepared @ 9.99

    $details = $service->loadCartDetails($sessionId);

    $hotTax = round(12.99 * 1 * 0.0825, 2);  // 1.07
    $coldTax = round(9.99 * 1 * 0.0625, 2);  // 0.62
    $expectedTax = round($hotTax + $coldTax, 2); // 1.69

    expect($details['subtotal'])->toBe(round(12.99 + 9.99, 2));
    expect($details['tax'])->toBe($expectedTax);
    expect($details['total'])->toBe(round(12.99 + 9.99 + $expectedTax, 2));
});

test('tax breakdown contains per-item details', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // hot_prepared
    $service->addItem($sessionId, 2); // cold_prepared

    $details = $service->loadCartDetails($sessionId);
    $breakdown = $details['taxBreakdown'];

    expect($breakdown)->toHaveCount(2);
    expect($breakdown[0]['tax_category'])->toBe('hot_prepared');
    expect($breakdown[0]['rate'])->toBe(0.0825);
    expect($breakdown[1]['tax_category'])->toBe('cold_prepared');
    expect($breakdown[1]['rate'])->toBe(0.0625);
});

test('increasing quantity recalculates tax correctly', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // Grilled Burger x1

    $details = $service->loadCartDetails($sessionId);
    $cartItemId = $details['items'][0]['id'];
    $cart = $service->getCart($sessionId);

    $service->updateQuantity($cartItemId, (int) $cart->id, 3); // Burger x3

    $details = $service->loadCartDetails($sessionId);

    $expectedTax = round(12.99 * 3 * 0.0825, 2); // 3.22
    expect($details['tax'])->toBe($expectedTax);
    expect($details['subtotal'])->toBe(round(12.99 * 3, 2));
});

test('multiple hot items sum taxes from same category', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // Grilled Burger, hot_prepared @ 12.99
    $service->addItem($sessionId, 3); // Fish & Chips, hot_prepared @ 14.50

    $details = $service->loadCartDetails($sessionId);

    $burgerTax = round(12.99 * 1 * 0.0825, 2);  // 1.07
    $fishTax = round(14.50 * 1 * 0.0825, 2);    // 1.20
    $expectedTax = round($burgerTax + $fishTax, 2); // 2.27

    expect($details['tax'])->toBe($expectedTax);
});

test('empty cart has zero tax', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();

    $details = $service->loadCartDetails($sessionId);

    expect($details['tax'])->toBe(0);
    expect($details['subtotal'])->toBe(0);
    expect($details['total'])->toBe(0);
    expect($details['items'])->toBeEmpty();
});

test('removing item recalculates tax', function () {
    $sessionId = 'tax-test-session';
    $service = new CartService();
    $service->addItem($sessionId, 1); // hot
    $service->addItem($sessionId, 2); // cold

    $details = $service->loadCartDetails($sessionId);
    $coldItemId = $details['items'][1]['id'];
    $cart = $service->getCart($sessionId);

    $service->removeItem($coldItemId, (int) $cart->id);

    $details = $service->loadCartDetails($sessionId);

    // Only hot item remains
    $expectedTax = round(12.99 * 1 * 0.0825, 2);
    expect($details['tax'])->toBe($expectedTax);
    expect($details['subtotal'])->toBe(12.99);
});
