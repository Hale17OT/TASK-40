<?php

declare(strict_types=1);

namespace App\Domain\Risk;

class RateLimitEvaluator
{
    /**
     * Check if a count exceeds the configured limit.
     *
     * @param int $currentCount Current number of events in the window
     * @param int $maxAllowed Maximum allowed events in the window
     * @return bool True if rate limit is exceeded
     */
    public function isExceeded(int $currentCount, int $maxAllowed): bool
    {
        return $currentCount >= $maxAllowed;
    }

    /**
     * Determine the rate limit key for a given identifier and action.
     */
    public function buildKey(string $type, string $identifier, string $action): string
    {
        return "rate_limit:{$type}:{$action}:{$identifier}";
    }
}
