<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
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

test('TrackEventUseCase records analytics event', function () {
    $tracker = new \App\Application\Analytics\TrackEventUseCase();
    $tracker->execute(
        eventType: 'test_event',
        sessionId: 'test-session',
        payload: ['key' => 'value'],
    );

    $event = DB::table('analytics_events')->where('event_type', 'test_event')->first();
    expect($event)->not->toBeNull();
    expect($event->session_id)->toBe('test-session');
});

test('add_to_cart event is tracked via CartService', function () {
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'track-test', 'created_at' => now(), 'updated_at' => now()]);

    $service = new \App\Application\Cart\CartService();
    $service->addItem('track-test', 1);

    $event = DB::table('analytics_events')->where('event_type', 'add_to_cart')->first();
    expect($event)->not->toBeNull();
    expect($event->session_id)->toBe('track-test');
});

test('order_placed event is tracked on order creation', function () {
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'order-track', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 10.00, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);

    $event = DB::table('analytics_events')->where('event_type', 'order_placed')->first();
    expect($event)->not->toBeNull();
});

test('ComputeAnalyticsUseCase includes retention metrics', function () {
    // Create events from same session on multiple days
    DB::table('analytics_events')->insert([
        ['event_type' => 'page_view', 'session_id' => 'retained-session', 'trace_id' => \Illuminate\Support\Str::uuid(), 'created_at' => now()->subDays(2)],
        ['event_type' => 'page_view', 'session_id' => 'retained-session', 'trace_id' => \Illuminate\Support\Str::uuid(), 'created_at' => now()->subDay()],
        ['event_type' => 'page_view', 'session_id' => 'one-time-session', 'trace_id' => \Illuminate\Support\Str::uuid(), 'created_at' => now()->subDay()],
    ]);

    $useCase = new \App\Application\Analytics\ComputeAnalyticsUseCase();
    $result = $useCase->execute(
        now()->subDays(7)->toDateTimeString(),
        now()->toDateTimeString(),
    );

    expect($result)->toHaveKey('retention_rate');
    expect($result)->toHaveKey('returning_sessions');
    expect($result['returning_sessions'])->toBe(1); // retained-session appeared on 2 days
    expect($result['retention_rate'])->toBeGreaterThan(0);
});

test('page view tracking fires on web request', function () {
    $this->get('/');

    // Analytics middleware should have tracked a page_view
    $event = DB::table('analytics_events')->where('event_type', 'page_view')->first();
    expect($event)->not->toBeNull();
});
