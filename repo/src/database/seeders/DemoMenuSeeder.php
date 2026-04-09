<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('menu_categories')->count() > 0) {
            return;
        }

        // Categories
        $categories = [
            ['name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sides', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Salads', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beverages', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Desserts', 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('menu_categories')->insert($categories);

        // Menu Items
        $items = [
            // Burgers (category 1)
            ['sku' => 'BRG-001', 'menu_category_id' => 1, 'name' => 'Classic Cheeseburger', 'description' => 'Angus beef patty with cheddar cheese, lettuce, tomato, and our secret sauce', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false])],
            ['sku' => 'BRG-002', 'menu_category_id' => 1, 'name' => 'Spicy Jalapeño Burger', 'description' => 'Angus beef with pepper jack cheese, jalapeños, and chipotle mayo', 'price' => 14.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 3, 'contains_nuts' => false])],
            ['sku' => 'BRG-003', 'menu_category_id' => 1, 'name' => 'Veggie Burger', 'description' => 'Plant-based patty with avocado, sprouts, and tahini dressing', 'price' => 13.49, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => true])],
            ['sku' => 'BRG-004', 'menu_category_id' => 1, 'name' => 'Gluten-Free Burger', 'description' => 'Beef patty on a gluten-free bun with all the fixings', 'price' => 15.49, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => false])],

            // Sides (category 2)
            ['sku' => 'SDE-001', 'menu_category_id' => 2, 'name' => 'French Fries', 'description' => 'Crispy golden fries with sea salt', 'price' => 4.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => false])],
            ['sku' => 'SDE-002', 'menu_category_id' => 2, 'name' => 'Onion Rings', 'description' => 'Beer-battered onion rings', 'price' => 5.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false])],
            ['sku' => 'SDE-003', 'menu_category_id' => 2, 'name' => 'Sweet Potato Fries', 'description' => 'Crispy sweet potato fries with honey mustard', 'price' => 5.49, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => false])],

            // Salads (category 3)
            ['sku' => 'SLD-001', 'menu_category_id' => 3, 'name' => 'Caesar Salad', 'description' => 'Romaine lettuce, parmesan, croutons, and Caesar dressing', 'price' => 9.99, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false])],
            ['sku' => 'SLD-002', 'menu_category_id' => 3, 'name' => 'Thai Peanut Salad', 'description' => 'Mixed greens with peanut dressing, cilantro, and crushed peanuts', 'price' => 11.49, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 1, 'contains_nuts' => true])],

            // Beverages (category 4)
            ['sku' => 'BEV-001', 'menu_category_id' => 4, 'name' => 'Fresh Lemonade', 'description' => 'Freshly squeezed lemonade', 'price' => 3.99, 'tax_category' => 'beverage', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => false])],
            ['sku' => 'BEV-002', 'menu_category_id' => 4, 'name' => 'Iced Tea', 'description' => 'Southern-style sweet iced tea', 'price' => 2.99, 'tax_category' => 'beverage', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => true, 'spicy_level' => 0, 'contains_nuts' => false])],

            // Desserts (category 5)
            ['sku' => 'DST-001', 'menu_category_id' => 5, 'name' => 'Chocolate Brownie', 'description' => 'Warm chocolate brownie with walnuts and vanilla ice cream', 'price' => 7.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => true])],
            ['sku' => 'DST-002', 'menu_category_id' => 5, 'name' => 'Key Lime Pie', 'description' => 'Tangy key lime pie with whipped cream', 'price' => 6.99, 'tax_category' => 'cold_prepared', 'is_active' => true, 'attributes' => json_encode(['gluten_free' => false, 'spicy_level' => 0, 'contains_nuts' => false])],
        ];

        foreach ($items as &$item) {
            $item['created_at'] = now();
            $item['updated_at'] = now();
        }

        DB::table('menu_items')->insert($items);

        // Trending Searches
        $trending = [
            ['term' => 'burger', 'sort_order' => 1, 'pinned_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['term' => 'salad', 'sort_order' => 2, 'pinned_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['term' => 'fries', 'sort_order' => 3, 'pinned_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['term' => 'gluten-free', 'sort_order' => 4, 'pinned_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['term' => 'dessert', 'sort_order' => 5, 'pinned_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('trending_searches')->insert($trending);
    }
}
