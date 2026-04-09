<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Application\Order\TransitionOrderUseCase;
use App\Domain\Auth\StepUpVerifier;
use App\Domain\Payment\HmacSigner;
use App\Domain\Payment\Exceptions\ExpiredNonceException;
use App\Domain\Payment\Exceptions\ReplayedNonceException;
use App\Domain\Payment\Exceptions\TamperedSignatureException;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentUseCase
{
    public function __construct(
        private readonly HmacSigner $signer,
        private readonly StepUpVerifier $stepUpVerifier,
        private readonly TransitionOrderUseCase $transitionOrder,
    ) {}

    /**
     * @return array{confirmation_id: int, order_status: string, idempotent: bool}
     */
    public function execute(
        string $reference,
        string $hmacSignature,
        string $nonce,
        string $method,
        int $confirmedBy,
        string $actorRole,
        int $expectedVersion,
        ?string $notes = null,
        ?string $managerPin = null,
        ?string $managerPinHash = null,
    ): array {
        return DB::transaction(function () use ($reference, $hmacSignature, $nonce, $method, $confirmedBy, $actorRole, $expectedVersion, $notes, $managerPin, $managerPinHash) {
            $intent = DB::table('payment_intents')
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$intent) {
                throw new \App\Application\Exceptions\BusinessException('Payment intent not found.', 'NOT_FOUND', 404);
            }

            // Idempotency check — if already confirmed, return existing
            if ($intent->status === 'confirmed') {
                $existingConfirmation = DB::table('payment_confirmations')
                    ->where('payment_intent_id', $intent->id)
                    ->first();

                return [
                    'confirmation_id' => $existingConfirmation ? $existingConfirmation->id : 0,
                    'order_status' => 'settled',
                    'idempotent' => true,
                ];
            }

            if ($intent->status === 'canceled') {
                throw new \App\Application\Exceptions\BusinessException('Payment intent was canceled.', 'PAYMENT_CANCELED', 422);
            }

            if ($intent->status === 'failed') {
                throw new \App\Application\Exceptions\BusinessException('Payment intent has failed.', 'PAYMENT_FAILED', 422);
            }

            // Check nonce replay — if this nonce was already consumed, reject
            if ($intent->nonce_used_at !== null) {
                throw new ReplayedNonceException();
            }

            // Check expiry
            if (now()->gt($intent->expires_at)) {
                DB::table('payment_intents')
                    ->where('id', $intent->id)
                    ->update(['status' => 'failed', 'updated_at' => now()]);

                throw new ExpiredNonceException(config('harborbite.payment.hmac_expiry_seconds', 300));
            }

            // Verify HMAC using the persisted signed timestamp
            $signedAt = $intent->signed_at ?? (int) strtotime($intent->created_at);
            $this->signer->verify(
                $hmacSignature,
                [
                    'reference' => $reference,
                    'amount' => (float) $intent->amount,
                    'order_id' => (int) $intent->order_id,
                ],
                $nonce,
                $signedAt,
            );

            // Mark nonce as consumed and intent as confirmed
            DB::table('payment_intents')
                ->where('id', $intent->id)
                ->update([
                    'status' => 'confirmed',
                    'nonce_used_at' => now(),
                    'updated_at' => now(),
                ]);

            // Create confirmation record
            $confirmationId = DB::table('payment_confirmations')->insertGetId([
                'payment_intent_id' => $intent->id,
                'confirmed_by' => $confirmedBy,
                'method' => $method,
                'notes' => $notes ? encrypt($notes) : null,
                'created_at' => now(),
            ]);

            // Fetch order for ambiguous check
            $order = DB::table('orders')
                ->where('id', $intent->order_id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \App\Application\Exceptions\BusinessException('Associated order not found.', 'NOT_FOUND', 404);
            }

            // Check for ambiguous settlement (amount mismatch or non-served status)
            $isAmbiguous = abs((float) $intent->amount - (float) $order->total) > 0.01
                || $order->status !== 'served';

            if ($isAmbiguous) {
                // Only manager/administrator can approve ambiguous settlements
                if (!in_array($actorRole, ['manager', 'administrator'], true)) {
                    throw new \App\Application\Exceptions\BusinessException(
                        'Ambiguous settlement requires manager or administrator role.',
                        'FORBIDDEN',
                        403,
                    );
                }

                if (!$managerPin || !$managerPinHash) {
                    throw new \App\Application\Exceptions\BusinessException(
                        'Ambiguous settlement requires manager PIN approval (amount mismatch or non-standard status).',
                        'STEP_UP_REQUIRED',
                        403,
                    );
                }

                if (!$this->stepUpVerifier->verify($managerPin, $managerPinHash)) {
                    throw new \App\Application\Exceptions\BusinessException('Incorrect manager PIN for ambiguous settlement.', 'STEP_UP_FAILED', 403);
                }

                DB::table('privilege_escalation_logs')->insert([
                    'action' => 'settle_ambiguous',
                    'order_id' => $order->id,
                    'manager_id' => $confirmedBy,
                    'manager_pin_hash' => $managerPinHash,
                    'reason' => $order->status !== 'served'
                        ? "Settlement from non-served status: {$order->status}"
                        : sprintf('Payment amount $%.2f does not match order total $%.2f', (float) $intent->amount, (float) $order->total),
                    'metadata' => json_encode([
                        'payment_reference' => $reference,
                        'intent_amount' => (float) $intent->amount,
                        'order_total' => (float) $order->total,
                        'order_status' => $order->status,
                    ]),
                    'created_at' => now(),
                ]);
            }

            // Route settle through authoritative TransitionOrderUseCase
            // (enforces OrderStateMachine, expected_version, role checks)
            $this->transitionOrder->execute(
                orderId: (int) $order->id,
                targetStatus: 'settled',
                expectedVersion: $expectedVersion,
                actorRole: $actorRole,
                actorId: $confirmedBy,
            );

            return [
                'confirmation_id' => $confirmationId,
                'order_status' => 'settled',
                'idempotent' => false,
            ];
        });
    }
}
