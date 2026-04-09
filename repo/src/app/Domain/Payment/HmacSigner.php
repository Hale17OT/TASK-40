<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Payment\Exceptions\ExpiredNonceException;
use App\Domain\Payment\Exceptions\TamperedSignatureException;
use App\Domain\Payment\Exceptions\ReplayedNonceException;

class HmacSigner
{
    public function __construct(
        private readonly string $key,
        private readonly int $expirySeconds = 300,
    ) {}

    /**
     * Sign parameters with HMAC-SHA256.
     *
     * @return array{signature: string, nonce: string, timestamp: int}
     */
    public function sign(array $params, ?string $nonce = null): array
    {
        $nonce = $nonce ?? bin2hex(random_bytes(16));
        $timestamp = time();

        $payload = $this->buildPayload($params, $nonce, $timestamp);
        $signature = hash_hmac('sha256', $payload, $this->key);

        return [
            'signature' => $signature,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Verify HMAC signature.
     *
     * @throws TamperedSignatureException
     * @throws ExpiredNonceException
     */
    public function verify(string $signature, array $params, string $nonce, int $timestamp): bool
    {
        // Check expiry
        if (abs(time() - $timestamp) > $this->expirySeconds) {
            throw new ExpiredNonceException($this->expirySeconds);
        }

        // Recompute signature
        $payload = $this->buildPayload($params, $nonce, $timestamp);
        $expected = hash_hmac('sha256', $payload, $this->key);

        if (!hash_equals($expected, $signature)) {
            throw new TamperedSignatureException();
        }

        return true;
    }

    private function buildPayload(array $params, string $nonce, int $timestamp): string
    {
        // Sort params deterministically
        ksort($params);
        $paramString = http_build_query($params);
        return "{$paramString}|{$nonce}|{$timestamp}";
    }

    public function getExpirySeconds(): int
    {
        return $this->expirySeconds;
    }
}
