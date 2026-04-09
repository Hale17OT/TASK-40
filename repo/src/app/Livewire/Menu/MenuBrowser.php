<?php

declare(strict_types=1);

namespace App\Livewire\Menu;

use App\Application\Search\SearchMenuUseCase;
use App\Domain\Search\SearchQuery;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MenuBrowser extends Component
{
    public string $search = '';
    public ?float $priceMin = null;
    public ?float $priceMax = null;
    public ?int $categoryId = null;
    public string $sort = 'relevance';
    public array $allergenExclusions = [];
    public ?int $maxSpicyLevel = null; // null = no limit; 0 = not spicy; 1-3 = mild/medium/hot
    public int $page = 1;

    // Result state
    public array $items = [];
    public int $total = 0;
    public array $trending = [];
    public array $categories = [];
    public bool $blocked = false;
    public ?string $blockMessage = null;
    public ?string $suggestion = null;
    public array $recentSearches = [];

    public function mount(): void
    {
        $this->categories = DB::table('menu_categories')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn ($c) => (array) $c)
            ->toArray();

        $this->performSearch();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function updatedPriceMin(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function updatedPriceMax(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function updatedCategoryId(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function updatedSort(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function toggleAllergen(string $allergen): void
    {
        if (in_array($allergen, $this->allergenExclusions)) {
            $this->allergenExclusions = array_values(array_diff($this->allergenExclusions, [$allergen]));
        } else {
            $this->allergenExclusions[] = $allergen;
        }
        $this->page = 1;
        $this->performSearch();
    }

    public function updatedMaxSpicyLevel(): void
    {
        $this->page = 1;
        $this->performSearch();
    }

    public function nextPage(): void
    {
        $this->page++;
        $this->performSearch();
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->performSearch();
        }
    }

    public function selectTrending(string $term): void
    {
        $this->search = $term;
        $this->page = 1;
        $this->performSearch();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->priceMin = null;
        $this->priceMax = null;
        $this->categoryId = null;
        $this->sort = 'relevance';
        $this->allergenExclusions = [];
        $this->maxSpicyLevel = null;
        $this->page = 1;
        $this->performSearch();
    }

    private function performSearch(): void
    {
        $useCase = app(SearchMenuUseCase::class);

        $locationId = session('kiosk_location_id') ?? config('harborbite.location_id');

        $query = new SearchQuery(
            keyword: $this->search,
            priceMin: $this->priceMin,
            priceMax: $this->priceMax,
            categoryId: $this->categoryId,
            allergenExclusions: $this->allergenExclusions,
            maxSpicyLevel: $this->maxSpicyLevel,
            sort: $this->sort,
            page: $this->page,
            locationId: $locationId ? (int) $locationId : null,
        );

        $result = $useCase->execute($query);

        $this->items = $result['items'];
        $this->total = $result['total'];
        $this->trending = $result['trending'];
        $this->blocked = $result['blocked'] ?? false;
        $this->blockMessage = $result['block_message'] ?? null;
        $this->suggestion = $result['suggestion'] ?? null;

        // Dispatch to browser for localStorage persistence
        if ($this->search && !$this->blocked) {
            $this->dispatch('search-performed', keyword: $this->search);
        }
    }

    public function render()
    {
        return view('livewire.menu.menu-browser');
    }
}
