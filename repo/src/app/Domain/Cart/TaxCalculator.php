<?php

declare(strict_types=1);

namespace App\Domain\Cart;

class TaxCalculator
{
    /**
     * @param array $taxRules Array of ['category' => string, 'rate' => float]
     * @param float $defaultRate Fallback rate if no rule matches
     */
    public function __construct(
        private readonly array $taxRules = [],
        private readonly float $defaultRate = 0.0825,
    ) {}

    /**
     * Calculate tax for a line item.
     */
    public function calculateItemTax(float $price, int $quantity, string $taxCategory): float
    {
        $rate = $this->getRateForCategory($taxCategory);
        return round($price * $quantity * $rate, 2);
    }

    /**
     * Calculate total tax for a cart.
     *
     * @param array $items Each item: ['price' => float, 'quantity' => int, 'tax_category' => string]
     */
    public function calculateCartTax(array $items): float
    {
        $totalTax = 0.0;
        foreach ($items as $item) {
            $totalTax += $this->calculateItemTax(
                (float) $item['price'],
                (int) $item['quantity'],
                $item['tax_category'],
            );
        }
        return round($totalTax, 2);
    }

    /**
     * Get tax breakdown per item.
     *
     * @param array $items Each item: ['price' => float, 'quantity' => int, 'tax_category' => string, 'name' => string]
     * @return array Each entry: ['name' => string, 'tax_category' => string, 'rate' => float, 'tax_amount' => float]
     */
    public function getBreakdown(array $items): array
    {
        $breakdown = [];
        foreach ($items as $item) {
            $rate = $this->getRateForCategory($item['tax_category']);
            $breakdown[] = [
                'name' => $item['name'] ?? '',
                'tax_category' => $item['tax_category'],
                'rate' => $rate,
                'tax_amount' => $this->calculateItemTax(
                    (float) $item['price'],
                    (int) $item['quantity'],
                    $item['tax_category'],
                ),
            ];
        }
        return $breakdown;
    }

    public function getRateForCategory(string $category): float
    {
        foreach ($this->taxRules as $rule) {
            if ($rule['category'] === $category) {
                return (float) $rule['rate'];
            }
        }
        return $this->defaultRate;
    }
}
