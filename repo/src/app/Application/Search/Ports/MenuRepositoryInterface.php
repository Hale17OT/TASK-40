<?php

declare(strict_types=1);

namespace App\Application\Search\Ports;

use App\Domain\Search\SearchQuery;

interface MenuRepositoryInterface
{
    /**
     * @return array{items: array, total: int}
     */
    public function search(SearchQuery $query, array $allergenExclusions = []): array;

    public function getTrendingTerms(?int $locationId = null): array;

    public function getCategories(): array;

    public function findById(int $id): ?array;
}
