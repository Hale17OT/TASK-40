<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Seed a category and items
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Test Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'sku' => 'T-002', 'menu_category_id' => 1, 'name' => 'Inactive Burger', 'description' => 'Test', 'price' => 9.99, 'tax_category' => 'hot_prepared', 'is_active' => false, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('menu_categories_id_seq', (SELECT MAX(id) FROM menu_categories))");
        DB::statement("SELECT setval('menu_items_id_seq', (SELECT MAX(id) FROM menu_items))");
    }
});

test('cart page loads', function () {
    $this->get('/cart')->assertStatus(200);
});

test('cart page shows empty state', function () {
    $this->get('/cart')->assertSee('Your cart is empty');
});
