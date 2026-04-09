<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Search\SearchMenuUseCase;
use App\Domain\Search\SearchQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(
        private readonly SearchMenuUseCase $searchMenu,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = new SearchQuery(
            keyword: $request->input('keyword', ''),
            priceMin: $request->has('price_min') ? (float) $request->input('price_min') : null,
            priceMax: $request->has('price_max') ? (float) $request->input('price_max') : null,
            categoryId: $request->has('category_id') ? (int) $request->input('category_id') : null,
            allergenExclusions: $request->input('allergen_exclusions', []),
            maxSpicyLevel: $request->has('max_spicy_level') ? (int) $request->input('max_spicy_level') : null,
            sort: $request->input('sort', 'relevance'),
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', config('harborbite.search.results_per_page', 20)),
        );

        $results = $this->searchMenu->execute($query);

        return response()->json([
            'data' => $results,
            'query' => [
                'keyword' => $query->keyword,
                'sort' => $query->sort,
                'page' => $query->page,
                'per_page' => $query->perPage,
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $repo = app(\App\Application\Search\Ports\MenuRepositoryInterface::class);

        return response()->json([
            'data' => $repo->getCategories(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $repo = app(\App\Application\Search\Ports\MenuRepositoryInterface::class);
        $item = $repo->findById($id);

        if (!$item) {
            return response()->json(['message' => 'Menu item not found.'], 404);
        }

        return response()->json(['data' => $item]);
    }
}
