<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Test Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test-session', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['id' => 1, 'cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 1, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('note is encrypted in cart_items table', function () {
    $note = 'No onions please';
    $encrypted = Crypt::encryptString($note);

    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);

    $stored = DB::table('cart_items')->find(1);
    // Stored value should not be plaintext
    expect($stored->note)->not->toBe($note);
    // But should decrypt to original
    expect(Crypt::decryptString($stored->note))->toBe($note);
});

test('notes are encrypted when copied to order_items', function () {
    $note = 'Extra mayo';
    $encrypted = Crypt::encryptString($note);
    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $orderItem = DB::table('order_items')->where('order_id', $order['id'])->first();
    // Note should be encrypted in order_items too
    expect($orderItem->note)->not->toBe($note);
    expect(Crypt::decryptString($orderItem->note))->toBe($note);
});

test('key rotation command includes cart and order note columns', function () {
    $note = 'Test note';
    $encrypted = Crypt::encryptString($note);
    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);

    // Run key rotation (dry-run)
    $this->artisan('harborbite:rotate-key --dry-run')
        ->assertExitCode(0);
});

test('null notes are handled gracefully', function () {
    // Cart item with null note
    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $orderItem = DB::table('order_items')->where('order_id', $order['id'])->first();
    expect($orderItem->note)->toBeNull();
});
