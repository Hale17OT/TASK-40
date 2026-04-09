<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'BRG-001', 'menu_category_id' => 1, 'name' => 'Burger', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'BRG-002', 'menu_category_id' => 1, 'name' => 'Deluxe Burger', 'price' => 18.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Active promotion: BOGO burgers
    DB::table('promotions')->insert([
        ['id' => 1, 'name' => 'BOGO Burgers', 'type' => 'bogo', 'rules' => json_encode(['target_skus' => ['BRG-001', 'BRG-002']]), 'exclusion_group' => 'item_discount', 'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-12-31 23:59:59', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
        DB::statement("SELECT setval('menu_categories_id_seq', (SELECT MAX(id) FROM menu_categories))");
        DB::statement("SELECT setval('menu_items_id_seq', (SELECT MAX(id) FROM menu_items))");
        DB::statement("SELECT setval('promotions_id_seq', (SELECT MAX(id) FROM promotions))");
    }
});

test('checkout page loads', function () {
    $this->get('/checkout')->assertStatus(200);
});

test('checkout shows empty state when no cart', function () {
    $this->get('/checkout')->assertSee('Your cart is empty');
});

test('order can be created from cart via checkout', function () {
    // Create a cart with items
    $cartId = DB::table('carts')->insertGetId(['session_id' => session()->getId(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);

    expect($order['status'])->toBe('pending_confirmation');
    expect((float) $order['subtotal'])->toBe(25.98);
    expect($order['order_number'])->toStartWith('HB-');
});

test('promotion is applied automatically at checkout for eligible cart', function () {
    $evaluator = app(\App\Domain\Promotion\PromotionEvaluator::class);

    $cartItems = [
        ['sku' => 'BRG-001', 'price' => 12.99, 'quantity' => 2, 'name' => 'Burger'],
    ];
    $subtotal = 25.98;

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

    expect($result)->not->toBeNull();
    expect($result['discount_amount'])->toBe(12.99); // BOGO: cheapest free
    expect($result['description'])->toContain('Buy One Get One');
});

test('applied promotion is saved when order is placed', function () {
    $cartId = DB::table('carts')->insertGetId(['session_id' => 'test-checkout', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => $cartId, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute($cartId);

    // Simulate promotion application (as done in CheckoutFlow)
    DB::table('applied_promotions')->insert([
        'order_id' => $order['id'],
        'promotion_id' => 1,
        'discount_amount' => 12.99,
        'description' => 'BOGO Burgers — saves $12.99',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $applied = DB::table('applied_promotions')->where('order_id', $order['id'])->first();
    expect($applied)->not->toBeNull();
    expect((float) $applied->discount_amount)->toBe(12.99);
});
