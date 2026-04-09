<?php

declare(strict_types=1);

namespace App\Domain\Risk;

class DeviceFingerprintGenerator
{
    public function __construct(
        private readonly string $salt,
    ) {}

    /**
     * Generate a fingerprint hash from device traits.
     */
    public function generate(string $userAgent, array $screenTraits = []): string
    {
        $normalized = $this->normalizeTraits($userAgent, $screenTraits);
        return hash('sha256', $this->salt . '|' . $normalized);
    }

    private function normalizeTraits(string $userAgent, array $screenTraits): string
    {
        $parts = [trim($userAgent)];

        // Sort screen traits for consistency
        ksort($screenTraits);
        foreach ($screenTraits as $key => $value) {
            $parts[] = "{$key}:{$value}";
        }

        return implode('|', $parts);
    }
}
