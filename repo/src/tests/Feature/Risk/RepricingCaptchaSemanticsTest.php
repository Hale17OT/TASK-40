<?php

use App\Application\Cart\CartService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();

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

test('repeated cart reads without price changes do not trigger CAPTCHA', function () {
    $sessionId = 'no-reprice-session';
    $service = new CartService();

    // Create cart with item
    $service->addItem($sessionId, 1);

    // Read cart details multiple times — prices have NOT changed
    $service->loadCartDetails($sessionId);
    $service->loadCartDetails($sessionId);
    $service->loadCartDetails($sessionId);
    $service->loadCartDetails($sessionId);
    $service->loadCartDetails($sessionId);

    // Should NOT trigger CAPTCHA because no actual repricing occurred
    expect($service->requiresRepricingCaptcha($sessionId))->toBeFalse();
});

test('CAPTCHA triggers only when prices actually change', function () {
    $sessionId = 'reprice-session';
    $service = new CartService();

    // Create cart with item at snapshot price 10.00
    $service->addItem($sessionId, 1);

    // First read — no price change
    $service->loadCartDetails($sessionId);
    expect($service->requiresRepricingCaptcha($sessionId))->toBeFalse();

    // Change menu item price to cause repricing detection
    DB::table('menu_items')->where('id', 1)->update(['price' => 11.00]);
    $service->loadCartDetails($sessionId);

    // Change price again
    DB::table('menu_items')->where('id', 1)->update(['price' => 12.00]);
    $service->loadCartDetails($sessionId);

    // Change price a third time within window — should now trigger
    DB::table('menu_items')->where('id', 1)->update(['price' => 13.00]);
    $service->loadCartDetails($sessionId);

    expect($service->requiresRepricingCaptcha($sessionId))->toBeTrue();
});

test('tax rule change triggers CAPTCHA without item price change', function () {
    $sessionId = 'tax-reprice-session';
    $service = new CartService();

    // Create cart with item
    $service->addItem($sessionId, 1);

    // First read — establishes baseline total
    $service->loadCartDetails($sessionId);
    expect($service->requiresRepricingCaptcha($sessionId))->toBeFalse();

    // Change tax rate — same item snapshot price, but total will shift
    DB::table('tax_rules')->where('category', 'hot_prepared')->update(['rate' => 0.15]);
    $service->loadCartDetails($sessionId);

    // Change tax rate again
    DB::table('tax_rules')->where('category', 'hot_prepared')->update(['rate' => 0.20]);
    $service->loadCartDetails($sessionId);

    // Third tax change within window — should now trigger
    DB::table('tax_rules')->where('category', 'hot_prepared')->update(['rate' => 0.25]);
    $service->loadCartDetails($sessionId);

    expect($service->requiresRepricingCaptcha($sessionId))->toBeTrue();
});

test('promotion window change triggers CAPTCHA via checkout flow', function () {
    Cache::flush();

    // Seed a cart with item via session
    $sessionId = session()->getId();
    $service = new CartService();
    $service->addItem($sessionId, 1);

    // Seed an active promotion
    DB::table('promotions')->insert([
        'name' => 'Flash Sale',
        'type' => 'percentage_off',
        'rules' => json_encode(['threshold' => 0, 'percentage' => 20]),
        'exclusion_group' => null,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // First checkout mount — establishes baseline total with promo discount
    $component = \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);
    $firstTotal = $component->get('cartSummary.total_after_discount');

    // Deactivate the promotion (simulates promo window closing)
    DB::table('promotions')->where('name', 'Flash Sale')->update(['is_active' => false]);

    // Re-mount checkout — total changes because promo no longer applies
    $component2 = \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);
    $secondTotal = $component2->get('cartSummary.total_after_discount');

    // Totals should differ (promo removed)
    expect($firstTotal)->not->toBe($secondTotal);

    // A repricing event should have been recorded for the promo change
    $cacheKey = "repricing_events:{$sessionId}";
    $events = Cache::get($cacheKey, []);
    expect($events)->not->toBeEmpty();
});

test('explicit recordRepricingEvent still works for external callers', function () {
    $sessionId = 'external-reprice-session';
    $service = new CartService();

    // Simulate external caller recording repricing events (e.g., admin price update)
    $service->recordRepricingEvent($sessionId);
    $service->recordRepricingEvent($sessionId);
    $service->recordRepricingEvent($sessionId);

    expect($service->requiresRepricingCaptcha($sessionId))->toBeTrue();
});
