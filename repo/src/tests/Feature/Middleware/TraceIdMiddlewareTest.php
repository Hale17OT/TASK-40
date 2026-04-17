<?php

test('response carries back an X-Trace-ID header when none supplied', function () {
    $response = $this->get('/');

    $traceId = $response->headers->get('X-Trace-ID');
    expect($traceId)->toBeString()->not->toBeEmpty();
    // Must be a valid UUID format
    expect($traceId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

test('incoming X-Trace-ID header is echoed back on the response', function () {
    $incoming = 'test-trace-12345';

    $response = $this->withHeaders(['X-Trace-ID' => $incoming])->get('/');

    expect($response->headers->get('X-Trace-ID'))->toBe($incoming);
});

test('different requests receive different trace IDs when none supplied', function () {
    $id1 = $this->get('/')->headers->get('X-Trace-ID');
    $id2 = $this->get('/')->headers->get('X-Trace-ID');

    expect($id1)->not->toBe($id2);
});
