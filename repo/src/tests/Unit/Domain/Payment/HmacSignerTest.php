<?php

use App\Domain\Payment\HmacSigner;
use App\Domain\Payment\Exceptions\TamperedSignatureException;
use App\Domain\Payment\Exceptions\ExpiredNonceException;

test('sign produces consistent output for same inputs', function () {
    $signer = new HmacSigner('test-key');
    $result = $signer->sign(['amount' => 25.00, 'reference' => 'ref-1'], 'fixed-nonce');
    expect($result['signature'])->toBeString()->toHaveLength(64);
    expect($result['nonce'])->toBe('fixed-nonce');
});

test('different keys produce different signatures', function () {
    $signer1 = new HmacSigner('key-a');
    $signer2 = new HmacSigner('key-b');
    $r1 = $signer1->sign(['amount' => 10], 'nonce1');
    $r2 = $signer2->sign(['amount' => 10], 'nonce1');
    expect($r1['signature'])->not->toBe($r2['signature']);
});

test('valid signature verifies successfully', function () {
    $signer = new HmacSigner('test-key', 600);
    $result = $signer->sign(['amount' => 25.00, 'order_id' => 1], 'my-nonce');
    $verified = $signer->verify($result['signature'], ['amount' => 25.00, 'order_id' => 1], 'my-nonce', $result['timestamp']);
    expect($verified)->toBeTrue();
});

test('tampered params fail verification', function () {
    $signer = new HmacSigner('test-key', 600);
    $result = $signer->sign(['amount' => 25.00], 'nonce1');
    $signer->verify($result['signature'], ['amount' => 50.00], 'nonce1', $result['timestamp']);
})->throws(TamperedSignatureException::class);

test('expired timestamp is rejected', function () {
    $signer = new HmacSigner('test-key', 300);
    $oldTimestamp = time() - 600; // 10 minutes ago
    $payload = "amount=25|nonce1|{$oldTimestamp}";
    $sig = hash_hmac('sha256', $payload, 'test-key');
    $signer->verify($sig, ['amount' => 25], 'nonce1', $oldTimestamp);
})->throws(ExpiredNonceException::class);

test('getExpirySeconds returns configured value', function () {
    $signer = new HmacSigner('key', 120);
    expect($signer->getExpirySeconds())->toBe(120);
});

test('param order does not matter for signing', function () {
    $signer = new HmacSigner('test-key');
    $r1 = $signer->sign(['b' => 2, 'a' => 1], 'nonce');
    $r2 = $signer->sign(['a' => 1, 'b' => 2], 'nonce');
    expect($r1['signature'])->toBe($r2['signature']);
});
