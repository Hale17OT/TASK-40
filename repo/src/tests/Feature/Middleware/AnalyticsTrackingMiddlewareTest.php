<?php

use Illuminate\Support\Facades\DB;

test('page_view analytics event is recorded for non-API 2xx requests', function () {
    $before = DB::table('analytics_events')->where('event_type', 'page_view')->count();

    $this->get('/')->assertStatus(200);

    $after = DB::table('analytics_events')->where('event_type', 'page_view')->count();
    expect($after)->toBeGreaterThan($before);

    $last = DB::table('analytics_events')
        ->where('event_type', 'page_view')
        ->orderByDesc('id')
        ->first();

    expect($last)->not->toBeNull();
    $payload = json_decode($last->payload, true);
    expect($payload['path'])->toBe('/');
});

test('API requests do NOT generate page_view analytics events', function () {
    $before = DB::table('analytics_events')->where('event_type', 'page_view')->count();

    $this->getJson('/api/time-sync')->assertStatus(200);

    $after = DB::table('analytics_events')->where('event_type', 'page_view')->count();
    expect($after)->toBe($before);
});

test('non-2xx responses do NOT generate page_view analytics events', function () {
    $before = DB::table('analytics_events')->where('event_type', 'page_view')->count();

    // /order/ with no matching token still renders the page (200) so we need
    // a true non-2xx response. Force a 404 via an admin route unauthenticated.
    $this->get('/admin/nonexistent-page')->assertStatus(404);

    $after = DB::table('analytics_events')->where('event_type', 'page_view')->count();
    expect($after)->toBe($before);
});
