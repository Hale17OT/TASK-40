<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tax_rules')->count() > 0) {
            return;
        }

        $rules = [
            [
                'category' => 'hot_prepared',
                'rate' => 0.0825,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'cold_prepared',
                'rate' => 0.0625,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'beverage',
                'rate' => 0.0825,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'packaged',
                'rate' => 0.0400,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('tax_rules')->insert($rules);
    }
}
