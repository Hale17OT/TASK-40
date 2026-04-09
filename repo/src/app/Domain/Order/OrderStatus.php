<?php

declare(strict_types=1);

namespace App\Domain\Order;

enum OrderStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case InPreparation = 'in_preparation';
    case Served = 'served';
    case Settled = 'settled';
    case Canceled = 'canceled';

    /**
     * Returns the valid next statuses from the current status.
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PendingConfirmation => [self::InPreparation, self::Canceled],
            self::InPreparation => [self::Served, self::Canceled],
            self::Served => [self::Settled, self::Canceled],
            self::Settled => [], // Terminal
            self::Canceled => [], // Terminal
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function isTerminal(): bool
    {
        return empty($this->transitions());
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'Pending Confirmation',
            self::InPreparation => 'In Preparation',
            self::Served => 'Served',
            self::Settled => 'Settled',
            self::Canceled => 'Canceled',
        };
    }

    /**
     * Roles allowed to trigger a transition TO this status.
     *
     * Workflow: Cashier confirms order (→InPreparation), Kitchen marks prepared
     * food as served (→Served), Cashier/Manager settles via payment confirmation
     * (→Settled). Kitchen does NOT confirm orders — their responsibility begins
     * after cashier confirmation, and ends when food is served.
     *
     * @return string[] Array of UserRole string values
     */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::InPreparation => ['cashier', 'manager', 'administrator'],       // Cashier confirms
            self::Served => ['kitchen', 'manager', 'administrator'],              // Kitchen marks served
            self::Settled => ['cashier', 'manager', 'administrator'],             // Via payment confirmation only
            self::Canceled => ['cashier', 'manager', 'administrator'],            // Step-up PIN for in_preparation/served
            self::PendingConfirmation => [],                                       // Initial state (kiosk-created)
        };
    }

    /**
     * Whether canceling FROM this status requires a manager PIN.
     */
    public function requiresStepUpForCancel(): bool
    {
        return match ($this) {
            self::InPreparation => true,
            self::Served => true,
            default => false,
        };
    }
}
