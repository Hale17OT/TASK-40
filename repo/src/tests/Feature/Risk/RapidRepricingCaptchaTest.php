<?php

use App\Application\Cart\CartService;
use App\Domain\Risk\CaptchaTriggerEvaluator;
use Illuminate\Support\Facades\Cache;

test('CaptchaTriggerEvaluator detects rapid repricing within window', function () {
    $evaluator = new CaptchaTriggerEvaluator(
        failedLoginThreshold: 5,
        rapidRepricingThreshold: 3,
        rapidRepricingWindowSeconds: 60,
    );

    $now = time();
    // 3 events within 60 seconds → should trigger
    $timestamps = [$now - 30, $now - 15, $now];
    expect($evaluator->shouldTriggerForRapidRepricing($timestamps))->toBeTrue();
});

test('CaptchaTriggerEvaluator ignores events outside window', function () {
    $evaluator = new CaptchaTriggerEvaluator(
        failedLoginThreshold: 5,
        rapidRepricingThreshold: 3,
        rapidRepricingWindowSeconds: 60,
    );

    $now = time();
    // 2 events within window, 1 outside → should not trigger
    $timestamps = [$now - 120, $now - 15, $now];
    expect($evaluator->shouldTriggerForRapidRepricing($timestamps))->toBeFalse();
});

test('CaptchaTriggerEvaluator does not trigger below threshold', function () {
    $evaluator = new CaptchaTriggerEvaluator(
        failedLoginThreshold: 5,
        rapidRepricingThreshold: 3,
        rapidRepricingWindowSeconds: 60,
    );

    // Only 2 events → below threshold of 3
    $timestamps = [time() - 10, time()];
    expect($evaluator->shouldTriggerForRapidRepricing($timestamps))->toBeFalse();
});

test('CartService records repricing events and triggers CAPTCHA check', function () {
    Cache::flush();
    $sessionId = 'test-repricing-session';

    $service = new CartService();

    // Initially no CAPTCHA required
    expect($service->requiresRepricingCaptcha($sessionId))->toBeFalse();

    // Simulate 3 rapid repricing events within 60 seconds
    $service->recordRepricingEvent($sessionId);
    $service->recordRepricingEvent($sessionId);
    $service->recordRepricingEvent($sessionId);

    // Now CAPTCHA should be required
    expect($service->requiresRepricingCaptcha($sessionId))->toBeTrue();
});

test('CartService repricing events expire and CAPTCHA is no longer required', function () {
    Cache::flush();
    $sessionId = 'test-repricing-expire';

    // Manually inject old timestamps that are outside the window
    $oldTimestamps = [time() - 400, time() - 350, time() - 310];
    Cache::put("repricing_events:{$sessionId}", $oldTimestamps, now()->addMinutes(5));

    $service = new CartService();
    // Events are old, should be pruned, and CAPTCHA not required
    expect($service->requiresRepricingCaptcha($sessionId))->toBeFalse();
});

test('repricing CAPTCHA blocks checkout until solved', function () {
    Cache::flush();

    // Seed minimal data for checkout
    \Illuminate\Support\Facades\DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    \Illuminate\Support\Facades\DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    \Illuminate\Support\Facades\DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Pre-fill repricing events to trigger CAPTCHA
    $sessionId = session()->getId();
    Cache::put("repricing_events:{$sessionId}", [time() - 10, time() - 5, time()], now()->addMinutes(5));

    $component = \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);

    // Should show CAPTCHA requirement
    $component->assertSet('requiresRepricingCaptcha', true);
    $component->assertSet('repricingCaptchaPassed', false);
});
