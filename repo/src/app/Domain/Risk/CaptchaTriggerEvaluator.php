<?php

declare(strict_types=1);

namespace App\Domain\Risk;

class CaptchaTriggerEvaluator
{
    public function __construct(
        private readonly int $failedLoginThreshold = 5,
        private readonly int $rapidRepricingThreshold = 3,
        private readonly int $rapidRepricingWindowSeconds = 60,
    ) {}

    /**
     * Check if CAPTCHA should be triggered based on failed login count.
     */
    public function shouldTriggerForFailedLogins(int $failedCount): bool
    {
        return $failedCount >= $this->failedLoginThreshold;
    }

    /**
     * Check if CAPTCHA should be triggered based on rapid re-pricing events.
     *
     * @param array $timestamps Unix timestamps of recent re-pricing events
     */
    public function shouldTriggerForRapidRepricing(array $timestamps): bool
    {
        if (count($timestamps) < $this->rapidRepricingThreshold) {
            return false;
        }

        // Sort descending to check most recent events
        rsort($timestamps);

        // Check if the threshold number of events occurred within the window
        $windowStart = $timestamps[0] - $this->rapidRepricingWindowSeconds;
        $eventsInWindow = 0;

        foreach ($timestamps as $ts) {
            if ($ts >= $windowStart) {
                $eventsInWindow++;
            }
        }

        return $eventsInWindow >= $this->rapidRepricingThreshold;
    }

    public function getFailedLoginThreshold(): int
    {
        return $this->failedLoginThreshold;
    }

    public function getRapidRepricingThreshold(): int
    {
        return $this->rapidRepricingThreshold;
    }
}
