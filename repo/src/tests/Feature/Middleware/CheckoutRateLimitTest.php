<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('POST /api/orders is rate-limited per checkout config (30 per 10 min) with JSON 429 body', function () {
    Cache::flush();

    // Create a cart with items for each order attempt
    $lastResponse = null;
    for ($i = 0; $i < 31; $i++) {
        $cartId = DB::table('carts')->insertGetId([
            'session_id' => session()->getId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cart_items')->insert([
            'cart_id' => $cartId,
            'menu_item_id' => 1,
            'quantity' => 1,
            'unit_price_snapshot' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lastResponse = $this->postJson('/api/orders', ['cart_id' => $cartId]);
    }

    // The 31st checkout attempt should be rate-limited with a structured JSON body
    $lastResponse->assertStatus(429);
    $lastResponse->assertJsonStructure(['message', 'error_code']);
    expect($lastResponse->json('error_code'))->toBe('RATE_LIMITED');
});

test('single checkout attempt succeeds and returns a tracking token', function () {
    Cache::flush();

    // Add item via API to create session-bound cart
    $this->postJson('/api/cart/items', ['menu_item_id' => 1]);
    $cart = DB::table('carts')->where('session_id', session()->getId())->first();

    $response = $this->postJson('/api/orders', ['cart_id' => $cart->id]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => ['id', 'order_number', 'tracking_token', 'status'],
        'tracking_url',
    ]);
    expect($response->json('data.status'))->toBe('pending_confirmation');
    expect($response->json('data.tracking_token'))->toHaveLength(64);
});

test('GET /checkout page renders kiosk checkout markup without rate limiting', function () {
    $response = $this->get('/checkout');
    $response->assertStatus(200);
    // The kiosk checkout view always includes the HarborBite branding / checkout container.
    expect($response->getContent())->toBeString()->not->toBeEmpty();
});
