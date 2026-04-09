<?php

use App\Domain\Risk\DeviceFingerprintGenerator;

test('same input produces same hash', function () {
    $gen = new DeviceFingerprintGenerator('test-salt');
    $hash1 = $gen->generate('Mozilla/5.0', ['width' => '1024', 'height' => '768']);
    $hash2 = $gen->generate('Mozilla/5.0', ['width' => '1024', 'height' => '768']);
    expect($hash1)->toBe($hash2);
});

test('different user agent produces different hash', function () {
    $gen = new DeviceFingerprintGenerator('test-salt');
    $hash1 = $gen->generate('Mozilla/5.0', ['width' => '1024']);
    $hash2 = $gen->generate('Chrome/100', ['width' => '1024']);
    expect($hash1)->not->toBe($hash2);
});

test('different salt produces different hash', function () {
    $gen1 = new DeviceFingerprintGenerator('salt-a');
    $gen2 = new DeviceFingerprintGenerator('salt-b');
    $hash1 = $gen1->generate('Mozilla/5.0', []);
    $hash2 = $gen2->generate('Mozilla/5.0', []);
    expect($hash1)->not->toBe($hash2);
});

test('empty screen traits still produces valid hash', function () {
    $gen = new DeviceFingerprintGenerator('test-salt');
    $hash = $gen->generate('Mozilla/5.0', []);
    expect($hash)->toBeString()->toHaveLength(64); // SHA-256 hex
});

test('screen traits order does not affect hash', function () {
    $gen = new DeviceFingerprintGenerator('test-salt');
    $hash1 = $gen->generate('Mozilla/5.0', ['width' => '1024', 'height' => '768']);
    $hash2 = $gen->generate('Mozilla/5.0', ['height' => '768', 'width' => '1024']);
    expect($hash1)->toBe($hash2);
});
