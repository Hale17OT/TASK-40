<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use Illuminate\Support\Facades\DB;

class ApplyPromotionUseCase
{
    /**
     * Apply a promotion to an order and update totals.
     */
    public function execute(int $orderId, int $promotionId, float $discountAmount, string $description): void
    {
        DB::table('applied_promotions')->insert([
            'order_id' => $orderId,
            'promotion_id' => $promotionId,
            'discount_amount' => $discountAmount,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = DB::table('orders')->find($orderId);
        $newTotal = round((float) $order->subtotal + (float) $order->tax - $discountAmount, 2);
        DB::table('orders')->where('id', $orderId)->update([
            'discount' => $discountAmount,
            'total' => max(0, $newTotal),
            'updated_at' => now(),
        ]);
    }
}
