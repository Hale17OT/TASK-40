<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Search\Ports\MenuRepositoryInterface;
use App\Domain\Search\SearchQuery;
use Illuminate\Support\Facades\DB;

class EloquentMenuRepository implements MenuRepositoryInterface
{
    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    public function search(SearchQuery $query, array $allergenExclusions = []): array
    {
        $builder = DB::table('menu_items')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->where('menu_items.is_active', true)
            ->where('menu_categories.is_active', true);

        // Full-text search
        if ($query->hasKeyword()) {
            if ($this->isPostgres()) {
                $tsQuery = $this->buildTsQuery($query->keyword);
                $builder->whereRaw("menu_items.search_vector @@ to_tsquery('english', ?)", [$tsQuery]);
            } else {
                // SQLite fallback: LIKE-based search
                $keyword = '%' . $query->keyword . '%';
                $builder->where(function ($q) use ($keyword) {
                    $q->where('menu_items.name', 'like', $keyword)
                      ->orWhere('menu_items.description', 'like', $keyword);
                });
            }
        }

        // Price range
        if ($query->priceMin !== null) {
            $builder->where('menu_items.price', '>=', $query->priceMin);
        }
        if ($query->priceMax !== null) {
            $builder->where('menu_items.price', '<=', $query->priceMax);
        }

        // Category filter
        if ($query->categoryId !== null) {
            $builder->where('menu_items.menu_category_id', $query->categoryId);
        }

        // Allergen exclusions (negative filters)
        foreach ($allergenExclusions as $exclusion) {
            $key = $exclusion['key'];

            if (isset($exclusion['exclude_when_gt'])) {
                // Numeric comparison: exclude items where attribute > threshold
                $threshold = $exclusion['exclude_when_gt'];
                if ($this->isPostgres()) {
                    $builder->whereRaw(
                        "NOT (COALESCE((menu_items.attributes->>?)::numeric, 0) > ?)",
                        [$key, $threshold]
                    );
                } else {
                    $builder->whereRaw(
                        "NOT (COALESCE(CAST(json_extract(menu_items.attributes, ?) AS REAL), 0) > ?)",
                        ['$.' . $key, $threshold]
                    );
                }
            } else {
                $excludeWhen = $exclusion['exclude_when'];

                if ($this->isPostgres()) {
                    $builder->whereRaw(
                        "NOT (menu_items.attributes @> ?::jsonb)",
                        [json_encode([$key => $excludeWhen])]
                    );
                } else {
                    // SQLite fallback: JSON extract
                    $jsonValue = $excludeWhen ? 'true' : 'false';
                    $builder->whereRaw(
                        "NOT (json_extract(menu_items.attributes, ?) = ?)",
                        ['$.' . $key, $jsonValue]
                    );
                }
            }
        }

        // Get total before pagination
        $total = $builder->count();

        // Sorting
        $selectColumns = [
            'menu_items.id',
            'menu_items.sku',
            'menu_items.name',
            'menu_items.description',
            'menu_items.price',
            'menu_items.tax_category',
            'menu_items.attributes',
            'menu_items.created_at',
            'menu_categories.name as category_name',
            'menu_categories.id as category_id',
        ];

        if ($query->hasKeyword() && $query->sort === 'relevance' && $this->isPostgres()) {
            $tsQuery = $this->buildTsQuery($query->keyword);
            $selectColumns[] = DB::raw("ts_rank(menu_items.search_vector, to_tsquery('english', " . DB::getPdo()->quote($tsQuery) . ")) as relevance_score");
            $builder->orderByDesc('relevance_score');
        } else {
            match ($query->sort) {
                'newest' => $builder->orderByDesc('menu_items.created_at'),
                'price_asc' => $builder->orderBy('menu_items.price'),
                'price_desc' => $builder->orderByDesc('menu_items.price'),
                default => $builder->orderBy('menu_items.name'),
            };
        }

        $items = $builder
            ->select($selectColumns)
            ->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage)
            ->get()
            ->map(function ($item) {
                $item->attributes = is_string($item->attributes) ? json_decode($item->attributes, true) : $item->attributes;
                return (array) $item;
            })
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function getTrendingTerms(?int $locationId = null): array
    {
        $query = DB::table('trending_searches')
            ->orderBy('sort_order')
            ->limit(20);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query->pluck('term')->toArray();
    }

    public function getCategories(): array
    {
        return DB::table('menu_categories')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn ($c) => (array) $c)
            ->toArray();
    }

    public function findById(int $id): ?array
    {
        $item = DB::table('menu_items')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->where('menu_items.id', $id)
            ->where('menu_items.is_active', true)
            ->select([
                'menu_items.*',
                'menu_categories.name as category_name',
            ])
            ->first();

        if (!$item) {
            return null;
        }

        $item = (array) $item;
        $item['attributes'] = is_string($item['attributes']) ? json_decode($item['attributes'], true) : $item['attributes'];
        return $item;
    }

    private function buildTsQuery(string $keyword): string
    {
        $words = preg_split('/\s+/', trim($keyword));
        $tsTerms = array_map(fn ($w) => preg_replace('/[^a-zA-Z0-9]/', '', $w) . ':*', $words);
        return implode(' & ', array_filter($tsTerms));
    }
}
