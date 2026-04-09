<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Domain\Payment\HmacSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePaymentIntentUseCase
{
    public function __construct(
        private readonly HmacSigner $signer,
    ) {}

    public function execute(int $orderId): array
    {
        $order = DB::table('orders')->find($orderId);
        if (!$order) {
            throw new \App\Application\Exceptions\BusinessException('Order not found.', 'NOT_FOUND', 404);
        }

        if (!in_array($order->status, ['pending_confirmation', 'in_preparation', 'served'])) {
            throw new \App\Application\Exceptions\BusinessException('Order is not in a payable state.', 'INVALID_STATE', 422);
        }

        // Check if there's already a pending intent
        $existing = DB::table('payment_intents')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return (array) $existing;
        }

        $reference = (string) Str::uuid();
        $amount = (float) $order->total;
        $nonce = bin2hex(random_bytes(16));

        $signed = $this->signer->sign([
            'reference' => $reference,
            'amount' => $amount,
            'order_id' => $orderId,
        ], $nonce);

        $expirySeconds = config('harborbite.payment.hmac_expiry_seconds', 300);

        $intentId = DB::table('payment_intents')->insertGetId([
            'order_id' => $orderId,
            'reference' => $reference,
            'amount' => $amount,
            'hmac_signature' => $signed['signature'],
            'signed_at' => $signed['timestamp'],
            'nonce' => $signed['nonce'],
            'expires_at' => now()->addSeconds($expirySeconds),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('payment_intents')->find($intentId);
    }
}
