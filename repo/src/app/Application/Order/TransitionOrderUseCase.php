<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Auth\StepUpVerifier;
use App\Domain\Order\OrderStateMachine;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\Exceptions\InsufficientRoleException;
use Illuminate\Support\Facades\DB;

class TransitionOrderUseCase
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly StepUpVerifier $stepUpVerifier,
    ) {}

    /**
     * @return array The updated order data
     */
    public function execute(
        int $orderId,
        string $targetStatus,
        int $expectedVersion,
        string $actorRole,
        int $actorId,
        ?string $managerPin = null,
        ?string $managerPinHash = null,
        ?string $cancelReason = null,
    ): array {
        return DB::transaction(function () use ($orderId, $targetStatus, $expectedVersion, $actorRole, $actorId, $managerPin, $managerPinHash, $cancelReason) {
            // Lock the order row for update
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                throw new \App\Application\Exceptions\BusinessException('Order not found.', 'NOT_FOUND', 404);
            }

            $currentStatus = OrderStatus::from($order->status);
            $target = OrderStatus::from($targetStatus);

            // Check step-up if needed — only manager/administrator can satisfy step-up
            $stepUpVerified = false;
            if ($target === OrderStatus::Canceled && $currentStatus->requiresStepUpForCancel()) {
                if (!in_array($actorRole, ['manager', 'administrator'], true)) {
                    throw new InsufficientRoleException(
                        role: $actorRole,
                        action: "cancel order in {$currentStatus->value} state (manager/admin required)",
                        requiredRoles: ['manager', 'administrator'],
                        requiresPin: true,
                    );
                }
                if (!$managerPin || !$managerPinHash) {
                    throw new InsufficientRoleException(
                        role: $actorRole,
                        action: "cancel order in {$currentStatus->value} state",
                        requiredRoles: ['manager', 'administrator'],
                        requiresPin: true,
                    );
                }
                if (!$this->stepUpVerifier->verify($managerPin, $managerPinHash)) {
                    throw new InsufficientRoleException(
                        role: $actorRole,
                        action: "cancel order (incorrect PIN)",
                        requiredRoles: ['manager', 'administrator'],
                        requiresPin: true,
                    );
                }
                $stepUpVerified = true;

                // Log privilege escalation
                DB::table('privilege_escalation_logs')->insert([
                    'action' => 'cancel_' . $currentStatus->value,
                    'order_id' => $orderId,
                    'manager_id' => $actorId,
                    'manager_pin_hash' => $managerPinHash,
                    'reason' => $cancelReason,
                    'metadata' => json_encode(['from_status' => $currentStatus->value]),
                    'created_at' => now(),
                ]);
            }

            // Enforce payment-confirmed prerequisite for settlement
            if ($target === OrderStatus::Settled) {
                $hasConfirmedPayment = DB::table('payment_intents')
                    ->where('order_id', $orderId)
                    ->where('status', 'confirmed')
                    ->exists();

                if (!$hasConfirmedPayment) {
                    throw new \App\Domain\Order\Exceptions\PaymentRequiredException();
                }
            }

            // Execute state machine transition
            $newVersion = $this->stateMachine->transition(
                $currentStatus,
                $target,
                (int) $order->version,
                $expectedVersion,
                $actorRole,
                $stepUpVerified,
            );

            // Update order
            $updateData = [
                'status' => $target->value,
                'version' => $newVersion,
                'updated_at' => now(),
            ];

            // Set actor fields
            match ($target) {
                OrderStatus::InPreparation => $updateData['confirmed_by'] = $actorId,
                OrderStatus::Served => $updateData['served_by'] = $actorId,
                OrderStatus::Settled => $updateData['settled_by'] = $actorId,
                OrderStatus::Canceled => (function () use (&$updateData, $actorId, $cancelReason) {
                    $updateData['canceled_by'] = $actorId;
                    $updateData['cancel_reason'] = $cancelReason;
                })(),
                default => null,
            };

            DB::table('orders')->where('id', $orderId)->update($updateData);

            // Lock items when moving to In Preparation
            if ($target === OrderStatus::InPreparation) {
                DB::table('order_items')
                    ->where('order_id', $orderId)
                    ->whereNull('locked_at')
                    ->update(['locked_at' => now()]);
            }

            // Log status change
            DB::table('order_status_logs')->insert([
                'order_id' => $orderId,
                'from_status' => $currentStatus->value,
                'to_status' => $target->value,
                'changed_by' => $actorId,
                'version_at_change' => $newVersion,
                'metadata' => json_encode([
                    'cancel_reason' => $cancelReason,
                    'step_up_verified' => $stepUpVerified,
                ]),
                'created_at' => now(),
            ]);

            // Track settled analytics event
            if ($target === OrderStatus::Settled) {
                (new \App\Application\Analytics\TrackEventUseCase())->execute(
                    eventType: 'order_settled',
                    payload: ['order_id' => $orderId, 'total' => (float) $order->total],
                );
            }

            return (array) DB::table('orders')->find($orderId);
        });
    }
}
