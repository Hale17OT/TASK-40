<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Cart\TaxCalculator;
use App\Domain\Promotion\PromotionEvaluator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrderUseCase
{
    public function execute(int $cartId): array
    {
        return DB::transaction(function () use ($cartId) {
            $cart = DB::table('carts')->find($cartId);
            if (!$cart) {
                throw new \App\Application\Exceptions\BusinessException('Cart not found.', 'NOT_FOUND', 404);
            }

            $cartItems = DB::table('cart_items')
                ->join('menu_items', 'cart_items.menu_item_id', '=', 'menu_items.id')
                ->where('cart_items.cart_id', $cartId)
                ->where('menu_items.is_active', true)
                ->select([
                    'cart_items.*',
                    'menu_items.name',
                    'menu_items.sku',
                    'menu_items.price as current_price',
                    'menu_items.tax_category',
                ])
                ->get();

            if ($cartItems->isEmpty()) {
                throw new \App\Application\Exceptions\BusinessException('Cart is empty.', 'EMPTY_CART', 422);
            }

            // Calculate totals
            $subtotal = 0;
            $taxItems = [];
            foreach ($cartItems as $item) {
                $lineTotal = round((float) $item->unit_price_snapshot * $item->quantity, 2);
                $subtotal += $lineTotal;
                $taxItems[] = [
                    'name' => $item->name,
                    'price' => (float) $item->unit_price_snapshot,
                    'quantity' => $item->quantity,
                    'tax_category' => $item->tax_category,
                ];
            }

            $taxRules = DB::table('tax_rules')
                ->whereDate('effective_from', '<=', now())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()))
                ->get(['category', 'rate'])
                ->map(fn ($r) => (array) $r)
                ->toArray();

            $taxCalc = new TaxCalculator($taxRules);
            $tax = $taxCalc->calculateCartTax($taxItems);
            $total = round($subtotal + $tax, 2);

            // Generate order number and tracking token
            $orderNumber = 'HB-' . strtoupper(Str::random(8));
            $trackingToken = bin2hex(random_bytes(32));

            // Create order
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $orderNumber,
                'tracking_token' => $trackingToken,
                'cart_id' => $cartId,
                'status' => 'pending_confirmation',
                'version' => 1,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => 0,
                'total' => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create order items (snapshot) — notes stay encrypted from cart
            foreach ($cartItems as $item) {
                $note = $item->note;
                // If note is plaintext (legacy), encrypt it
                if ($note !== null) {
                    try {
                        Crypt::decryptString($note);
                        // Already encrypted, keep as-is
                    } catch (\Throwable) {
                        $note = Crypt::encryptString($note);
                    }
                }

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'menu_item_id' => $item->menu_item_id,
                    'item_name' => $item->name,
                    'item_sku' => $item->sku,
                    'unit_price' => $item->unit_price_snapshot,
                    'quantity' => $item->quantity,
                    'tax_category' => $item->tax_category,
                    'flavor_preference' => $item->flavor_preference,
                    'note' => $note,
                    'locked_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Log initial status
            DB::table('order_status_logs')->insert([
                'order_id' => $orderId,
                'from_status' => '',
                'to_status' => 'pending_confirmation',
                'changed_by' => null,
                'version_at_change' => 1,
                'metadata' => json_encode(['source' => 'kiosk', 'cart_id' => $cartId]),
                'created_at' => now(),
            ]);

            // Evaluate and apply best automatic promotion atomically
            $appliedPromotion = null;
            $promotions = DB::table('promotions')
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->get()
                ->map(function ($p) {
                    $arr = (array) $p;
                    $arr['rules'] = json_decode($arr['rules'], true);
                    return $arr;
                })
                ->toArray();

            if (!empty($promotions)) {
                $promoItems = [];
                foreach ($cartItems as $item) {
                    $promoItems[] = [
                        'sku' => $item->sku,
                        'price' => (float) $item->unit_price_snapshot,
                        'quantity' => $item->quantity,
                        'name' => $item->name,
                    ];
                }

                $evaluator = app(PromotionEvaluator::class);
                $appliedPromotion = $evaluator->evaluate($promoItems, $promotions, $subtotal);

                if ($appliedPromotion) {
                    $discount = (float) $appliedPromotion['discount_amount'];

                    DB::table('applied_promotions')->insert([
                        'order_id' => $orderId,
                        'promotion_id' => $appliedPromotion['promotion_id'],
                        'discount_amount' => $discount,
                        'description' => $appliedPromotion['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $newTotal = round($subtotal + $tax - $discount, 2);
                    DB::table('orders')->where('id', $orderId)->update([
                        'discount' => $discount,
                        'total' => max(0, $newTotal),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Track analytics event
            (new \App\Application\Analytics\TrackEventUseCase())->execute(
                eventType: 'order_placed',
                sessionId: $cart->session_id,
                payload: ['order_id' => $orderId, 'total' => $total],
            );

            return (array) DB::table('orders')->find($orderId);
        });
    }
}
