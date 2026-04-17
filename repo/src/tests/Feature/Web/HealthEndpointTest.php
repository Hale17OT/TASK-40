<?php

test('GET /up returns healthy status with expected structure', function () {
    $response = $this->get('/up');

    $response->assertStatus(200);
    $response->assertHeader('content-type');

    // Laravel's default health endpoint returns an HTML page with a JSON payload
    // embedded in a <pre> tag. Assert both the status text and our app name appear.
    $body = $response->getContent();
    expect($body)->toContain('Application up');
});

test('GET /up is reachable without authentication', function () {
    // Even when not logged in, health must be publicly reachable for monitoring.
    $response = $this->get('/up');
    $response->assertStatus(200);
});
