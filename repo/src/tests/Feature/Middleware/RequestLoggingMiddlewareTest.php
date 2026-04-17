<?php

use Illuminate\Support\Facades\DB;

test('request metrics row is persisted for every HTTP request', function () {
    $before = DB::table('request_metrics')->count();

    $this->getJson('/api/time-sync')->assertStatus(200);

    $after = DB::table('request_metrics')->count();
    expect($after)->toBe($before + 1);

    $last = DB::table('request_metrics')->orderByDesc('id')->first();
    expect($last->method)->toBe('GET');
    expect($last->path)->toBe('api/time-sync');
    expect((int) $last->status_code)->toBe(200);
    expect((float) $last->duration_ms)->toBeGreaterThanOrEqual(0);
});

test('request metrics captures 4xx responses with correct status code', function () {
    $this->getJson('/api/menu/999999')->assertStatus(404);

    $last = DB::table('request_metrics')
        ->where('path', 'api/menu/999999')
        ->orderByDesc('id')
        ->first();

    expect($last)->not->toBeNull();
    expect((int) $last->status_code)->toBe(404);
});

test('request metrics captures POST method and path correctly', function () {
    $this->postJson('/api/cart/items', ['menu_item_id' => 0])->assertStatus(404);

    $last = DB::table('request_metrics')
        ->where('path', 'api/cart/items')
        ->where('method', 'POST')
        ->orderByDesc('id')
        ->first();

    expect($last)->not->toBeNull();
    expect($last->method)->toBe('POST');
});
