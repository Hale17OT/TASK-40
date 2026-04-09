<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannedWordSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('banned_words')->count() > 0) {
            return;
        }

        $words = [
            'scam', 'fraud', 'hack', 'exploit', 'cheat',
            'spam', 'phishing', 'malware', 'virus', 'porn',
            'xxx', 'gambling', 'casino', 'drugs', 'weapon',
        ];

        $records = array_map(fn ($word) => [
            'word' => $word,
            'created_at' => now(),
            'updated_at' => now(),
        ], $words);

        DB::table('banned_words')->insert($records);
    }
}
