<?php

declare(strict_types=1);

namespace App\Domain\Search;

class SearchQuery
{
    public function __construct(
        public readonly string $keyword = '',
        public readonly ?float $priceMin = null,
        public readonly ?float $priceMax = null,
        public readonly ?int $categoryId = null,
        public readonly array $allergenExclusions = [],
        public readonly ?int $maxSpicyLevel = null,
        public readonly string $sort = 'relevance',
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly ?int $locationId = null,
    ) {}

    public function hasKeyword(): bool
    {
        return trim($this->keyword) !== '';
    }

    public function hasPriceRange(): bool
    {
        return $this->priceMin !== null || $this->priceMax !== null;
    }

    public function hasAllergenExclusions(): bool
    {
        return !empty($this->allergenExclusions);
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->priceMin !== null && $this->priceMax !== null && $this->priceMin > $this->priceMax) {
            $errors[] = 'Minimum price cannot be greater than maximum price.';
        }
        if ($this->priceMin !== null && $this->priceMin < 0) {
            $errors[] = 'Minimum price cannot be negative.';
        }
        if (!in_array($this->sort, ['relevance', 'newest', 'price_asc', 'price_desc'])) {
            $errors[] = 'Invalid sort option.';
        }
        return $errors;
    }
}
