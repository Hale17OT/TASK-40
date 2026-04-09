<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('promotions')->count() > 0) {
            return;
        }

        $promotions = [
            [
                'name' => '10% Off Orders Over $30',
                'type' => 'percentage_off',
                'rules' => json_encode(['threshold' => 30.00, 'percentage' => 10]),
                'exclusion_group' => 'cart_discount',
                'starts_at' => '2026-01-01 00:00:00',
                'ends_at' => '2026-12-31 23:59:59',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '$5 Off Orders Over $50',
                'type' => 'flat_discount',
                'rules' => json_encode(['threshold' => 50.00, 'amount' => 5.00]),
                'exclusion_group' => 'cart_discount',
                'starts_at' => '2026-01-01 00:00:00',
                'ends_at' => '2026-12-31 23:59:59',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'BOGO Burgers',
                'type' => 'bogo',
                'rules' => json_encode(['target_skus' => ['BRG-001', 'BRG-002', 'BRG-003', 'BRG-004']]),
                'exclusion_group' => 'item_discount',
                'starts_at' => '2026-01-01 00:00:00',
                'ends_at' => '2026-12-31 23:59:59',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '50% Off Second Side',
                'type' => 'percentage_off_second',
                'rules' => json_encode(['target_skus' => ['SDE-001', 'SDE-002', 'SDE-003'], 'percentage' => 50]),
                'exclusion_group' => 'item_discount',
                'starts_at' => '2026-03-27 17:00:00',
                'ends_at' => '2026-03-27 20:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('promotions')->insert($promotions);
    }
}
