<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

test('max-length 140-char note encrypts and decrypts correctly via cart_items', function () {
    // Generate a 140-character note (maximum allowed by CartValidator)
    $note = str_repeat('A', 140);
    $encrypted = Crypt::encryptString($note);

    // Encrypted payload is much larger than 140 chars — TEXT column handles it
    expect(strlen($encrypted))->toBeGreaterThan(140);

    // Store encrypted note
    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);

    // Read back and verify
    $stored = DB::table('cart_items')->find(1);
    expect($stored->note)->toBe($encrypted);
    expect(Crypt::decryptString($stored->note))->toBe($note);
});

test('max-length note survives roundtrip through order_items', function () {
    $note = str_repeat('X', 140);
    $encrypted = Crypt::encryptString($note);

    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);

    $useCase = new \App\Application\Order\CreateOrderUseCase();
    $order = $useCase->execute(1);

    $orderItem = DB::table('order_items')->where('order_id', $order['id'])->first();
    expect($orderItem->note)->not->toBeNull();
    expect(Crypt::decryptString($orderItem->note))->toBe($note);
});

test('various note lengths encrypt and decrypt correctly', function () {
    $lengths = [1, 10, 50, 100, 139, 140];

    foreach ($lengths as $len) {
        $note = str_repeat('Z', $len);
        $encrypted = Crypt::encryptString($note);

        DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);
        $stored = DB::table('cart_items')->find(1);

        expect(Crypt::decryptString($stored->note))->toBe($note)
            ->and(strlen($stored->note))->toBeGreaterThan($len);
    }
});

test('unicode notes encrypt and decrypt correctly', function () {
    $note = 'No gluten please 🌾 — alérgico al maní';
    $encrypted = Crypt::encryptString($note);

    DB::table('cart_items')->where('id', 1)->update(['note' => $encrypted]);
    $stored = DB::table('cart_items')->find(1);

    expect(Crypt::decryptString($stored->note))->toBe($note);
});
