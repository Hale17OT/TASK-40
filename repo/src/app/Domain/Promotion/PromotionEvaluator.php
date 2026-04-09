<?php

declare(strict_types=1);

namespace App\Domain\Promotion;

class PromotionEvaluator
{
    /**
     * Evaluate all active promotions against cart items and find the best offer.
     *
     * Resolution Tree:
     * 1. Evaluate item-level discounts first (BOGO, % off second)
     * 2. Evaluate cart-level discounts second (% off total, flat off total)
     * 3. Calculate all valid combinations respecting mutual exclusion groups
     * 4. Select combination yielding lowest total ("best offer wins")
     *
     * @param array $cartItems Each: ['sku' => string, 'price' => float, 'quantity' => int, 'name' => string]
     * @param array $promotions Each: ['id' => int, 'name' => string, 'type' => string, 'rules' => array, 'exclusion_group' => ?string]
     * @param float $cartSubtotal
     * @return array|null ['promotion_id' => int, 'name' => string, 'discount_amount' => float, 'description' => string] or null
     */
    public function evaluate(array $cartItems, array $promotions, float $cartSubtotal): ?array
    {
        if (empty($cartItems) || empty($promotions)) {
            return null;
        }

        $candidates = [];

        // Step 1: Evaluate item-level promotions
        foreach ($promotions as $promo) {
            $type = PromoType::from($promo['type']);
            if (!$type->isItemLevel()) {
                continue;
            }

            $discount = $this->evaluatePromotion($promo, $cartItems, $cartSubtotal);
            if ($discount !== null && $discount['discount_amount'] > 0) {
                $candidates[] = $discount;
            }
        }

        // Step 2: Evaluate cart-level promotions
        foreach ($promotions as $promo) {
            $type = PromoType::from($promo['type']);
            if (!$type->isCartLevel()) {
                continue;
            }

            $discount = $this->evaluatePromotion($promo, $cartItems, $cartSubtotal);
            if ($discount !== null && $discount['discount_amount'] > 0) {
                $candidates[] = $discount;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Step 3: Filter by mutual exclusion and find best
        // Group candidates by exclusion_group
        // Within each group, keep only the best
        // Then pick the single best across all groups (best offer wins = one promo applied)
        $bestByGroup = [];
        $ungrouped = [];

        foreach ($candidates as $candidate) {
            $group = $candidate['exclusion_group'] ?? null;
            if ($group) {
                if (!isset($bestByGroup[$group]) || $candidate['discount_amount'] > $bestByGroup[$group]['discount_amount']) {
                    $bestByGroup[$group] = $candidate;
                }
            } else {
                $ungrouped[] = $candidate;
            }
        }

        // Merge best from each group + ungrouped
        $finalists = array_merge(array_values($bestByGroup), $ungrouped);

        // Step 4: Select the single best offer (highest discount amount)
        usort($finalists, fn ($a, $b) => $b['discount_amount'] <=> $a['discount_amount']);

        return $finalists[0] ?? null;
    }

    private function evaluatePromotion(array $promo, array $cartItems, float $cartSubtotal): ?array
    {
        $type = PromoType::from($promo['type']);
        $rules = $promo['rules'];

        $discount = match ($type) {
            PromoType::PercentageOff => $this->calcPercentageOff($rules, $cartSubtotal),
            PromoType::FlatDiscount => $this->calcFlatDiscount($rules, $cartSubtotal),
            PromoType::Bogo => $this->calcBogo($rules, $cartItems),
            PromoType::PercentageOffSecond => $this->calcPercentageOffSecond($rules, $cartItems),
        };

        if ($discount === null || $discount <= 0) {
            return null;
        }

        return [
            'promotion_id' => $promo['id'],
            'name' => $promo['name'],
            'type' => $promo['type'],
            'discount_amount' => round($discount, 2),
            'exclusion_group' => $promo['exclusion_group'] ?? null,
            'description' => $this->buildDescription($promo, $discount),
        ];
    }

    private function calcPercentageOff(array $rules, float $subtotal): ?float
    {
        $threshold = (float) ($rules['threshold'] ?? 0);
        $percentage = (float) ($rules['percentage'] ?? 0);

        if ($subtotal < $threshold || $percentage <= 0) {
            return null;
        }

        return round($subtotal * ($percentage / 100), 2);
    }

    private function calcFlatDiscount(array $rules, float $subtotal): ?float
    {
        $threshold = (float) ($rules['threshold'] ?? 0);
        $amount = (float) ($rules['amount'] ?? 0);

        if ($subtotal < $threshold || $amount <= 0) {
            return null;
        }

        return min($amount, $subtotal); // Can't discount more than subtotal
    }

    private function calcBogo(array $rules, array $cartItems): ?float
    {
        $targetSkus = $rules['target_skus'] ?? [];
        if (empty($targetSkus)) {
            return null;
        }

        // Find qualifying items
        $qualifyingItems = [];
        foreach ($cartItems as $item) {
            if (in_array($item['sku'], $targetSkus)) {
                // Expand by quantity
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $qualifyingItems[] = (float) $item['price'];
                }
            }
        }

        if (count($qualifyingItems) < 2) {
            return null;
        }

        // Sort ascending — cheapest item is free
        sort($qualifyingItems);

        // For every pair, the cheaper one is free
        $freeCount = intdiv(count($qualifyingItems), 2);
        $discount = 0;
        for ($i = 0; $i < $freeCount; $i++) {
            $discount += $qualifyingItems[$i];
        }

        return $discount;
    }

    private function calcPercentageOffSecond(array $rules, array $cartItems): ?float
    {
        $targetSkus = $rules['target_skus'] ?? [];
        $percentage = (float) ($rules['percentage'] ?? 50);

        if (empty($targetSkus)) {
            return null;
        }

        $qualifyingItems = [];
        foreach ($cartItems as $item) {
            if (in_array($item['sku'], $targetSkus)) {
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $qualifyingItems[] = (float) $item['price'];
                }
            }
        }

        if (count($qualifyingItems) < 2) {
            return null;
        }

        // Sort descending — discount applies to the cheaper (second) item
        rsort($qualifyingItems);

        // Every second item gets the percentage off
        $discount = 0;
        for ($i = 1; $i < count($qualifyingItems); $i += 2) {
            $discount += $qualifyingItems[$i] * ($percentage / 100);
        }

        return $discount;
    }

    private function buildDescription(array $promo, float $discount): string
    {
        $type = PromoType::from($promo['type']);
        $rules = $promo['rules'];

        return match ($type) {
            PromoType::PercentageOff => "{$rules['percentage']}% off orders over \${$rules['threshold']} — saves \$" . number_format($discount, 2),
            PromoType::FlatDiscount => "\${$rules['amount']} off orders over \${$rules['threshold']} — saves \$" . number_format($discount, 2),
            PromoType::Bogo => "Buy One Get One Free — saves \$" . number_format($discount, 2),
            PromoType::PercentageOffSecond => ($rules['percentage'] ?? 50) . "% off second item — saves \$" . number_format($discount, 2),
        };
    }
}
