<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Order\Exceptions\InvalidTransitionException;
use App\Domain\Order\Exceptions\StaleVersionException;
use App\Domain\Order\Exceptions\KitchenLockException;
use App\Domain\Order\Exceptions\InsufficientRoleException;

class OrderStateMachine
{
    /**
     * Validate and describe a state transition.
     *
     * @param OrderStatus $currentStatus Current order status
     * @param OrderStatus $targetStatus Desired status
     * @param int $currentVersion Current order version in DB
     * @param int $expectedVersion Version the client thinks is current
     * @param string $actorRole Role of the user attempting the transition
     * @param bool $stepUpVerified Whether manager PIN was verified
     *
     * @throws StaleVersionException
     * @throws InvalidTransitionException
     * @throws InsufficientRoleException
     *
     * @return int The new version number
     */
    public function transition(
        OrderStatus $currentStatus,
        OrderStatus $targetStatus,
        int $currentVersion,
        int $expectedVersion,
        string $actorRole,
        bool $stepUpVerified = false,
    ): int {
        // 1. Check version (optimistic concurrency)
        if ($currentVersion !== $expectedVersion) {
            throw new StaleVersionException(
                currentVersion: $currentVersion,
                expectedVersion: $expectedVersion,
                currentStatus: $currentStatus->value,
            );
        }

        // 2. Check if transition is valid
        if (!$currentStatus->canTransitionTo($targetStatus)) {
            throw new InvalidTransitionException(
                from: $currentStatus->value,
                to: $targetStatus->value,
            );
        }

        // 3. Check role authorization
        $allowedRoles = $targetStatus->allowedRoles();
        if (!empty($allowedRoles) && !in_array($actorRole, $allowedRoles, true)) {
            throw new InsufficientRoleException(
                role: $actorRole,
                action: "transition to {$targetStatus->value}",
                requiredRoles: $allowedRoles,
            );
        }

        // 4. Check step-up for cancellation
        if ($targetStatus === OrderStatus::Canceled && $currentStatus->requiresStepUpForCancel()) {
            if (!$stepUpVerified) {
                throw new InsufficientRoleException(
                    role: $actorRole,
                    action: "cancel order in {$currentStatus->value} state",
                    requiredRoles: ['manager', 'administrator'],
                    requiresPin: true,
                );
            }
        }

        // 5. Return incremented version
        return $currentVersion + 1;
    }
}
