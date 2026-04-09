<?php

declare(strict_types=1);

namespace App\Domain\Auth;

class StepUpVerifier
{
    /**
     * Verify a manager PIN against a bcrypt hash.
     * Uses password_verify (pure PHP, no Laravel dependency).
     */
    public function verify(string $plainPin, string $hashedPin): bool
    {
        if (empty($plainPin) || empty($hashedPin)) {
            return false;
        }

        return password_verify($plainPin, $hashedPin);
    }

    /**
     * Determine if a given action requires step-up verification.
     */
    public function requiresStepUp(string $action, array $context = []): bool
    {
        return match ($action) {
            'cancel_in_preparation' => true,
            'cancel_served' => true,
            'discount_override' => isset($context['discount_amount']) && $context['discount_amount'] > 20.00,
            'settle_ambiguous' => true,
            default => false,
        };
    }
}
