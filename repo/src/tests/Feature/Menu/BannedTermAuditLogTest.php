<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Test', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Burger', 'description' => 'Test', 'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('banned_words')->insert([
        ['word' => 'badword', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('blocked search term creates immutable rule_hit_log entry', function () {
    $response = $this->getJson('/api/menu/search?keyword=badword');
    $response->assertStatus(200);

    $blocked = $response->json('data.blocked');
    expect($blocked)->toBeTrue();

    // Verify rule_hit_logs entry was created
    $log = DB::table('rule_hit_logs')
        ->where('type', 'banned_term_blocked')
        ->first();

    expect($log)->not->toBeNull();
    $details = json_decode($log->details, true);
    expect($details['keyword'])->toBe('badword');
});

test('non-blocked search does not create rule_hit_log', function () {
    $this->getJson('/api/menu/search?keyword=burger');

    $count = DB::table('rule_hit_logs')
        ->where('type', 'banned_term_blocked')
        ->count();

    expect($count)->toBe(0);
});
