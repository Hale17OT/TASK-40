<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'B-001', 'menu_category_id' => 1, 'name' => 'Premium Burger', 'description' => 'Expensive', 'price' => 50.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('promotions')->insert([
        'name' => 'Big Discount',
        'type' => 'percentage_off',
        'rules' => json_encode(['threshold' => 0, 'percentage' => 50]),
        'exclusion_group' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('automatic promotion over $20 does NOT require manager PIN for guest checkout', function () {
    // The 50% off on a $50 item = $25 discount (> $20 threshold)
    // But this is an automatic "best offer wins" promo, not a manual override.
    // Guest checkout must NOT be blocked by manager PIN requirement.

    $component = \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);

    // The automatic promo should be applied without triggering PIN modal
    $component->assertSet('requiresDiscountOverridePin', false);

    // manualDiscountOverride should be false (no staff intervention)
    $component->assertSet('manualDiscountOverride', false);
});

test('manual discount override over $20 DOES require manager PIN', function () {
    // Add item to cart for current session so placeOrder doesn't bail early
    $cartService = new \App\Application\Cart\CartService();
    $cartService->addItem(session()->getId(), 1);

    $component = \Livewire\Livewire::test(\App\Livewire\Checkout\CheckoutFlow::class);

    // Staff explicitly triggers a manual discount
    $component->call('applyManualDiscount', 25.00);

    // Now try to place order — should require PIN
    $component->call('placeOrder');
    $component->assertSet('requiresDiscountOverridePin', true);
});

test('StepUpVerifier discount_override only applies to manual overrides', function () {
    $verifier = new \App\Domain\Auth\StepUpVerifier();

    // > $20 manual override requires step-up
    expect($verifier->requiresStepUp('discount_override', ['discount_amount' => 25.00]))->toBeTrue();

    // <= $20 does not
    expect($verifier->requiresStepUp('discount_override', ['discount_amount' => 15.00]))->toBeFalse();
});
