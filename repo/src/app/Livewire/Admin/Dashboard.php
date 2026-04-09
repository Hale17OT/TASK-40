<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Application\Analytics\ComputeAnalyticsUseCase;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public array $analytics = [];
    public array $alerts = [];
    public int $totalOrders = 0;
    public int $activeOrders = 0;

    // Trending term pinning
    public string $newTrendingTerm = '';
    public ?int $trendingLocationId = null;
    public array $trendingTerms = [];
    public ?string $trendingError = null;
    public ?string $trendingMessage = null;

    public const MAX_PINNED_TRENDING_PER_LOCATION = 20;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadData();
        $this->loadTrendingTerms();
    }

    public function updatedDateFrom(): void
    {
        $this->loadData();
    }

    public function updatedDateTo(): void
    {
        $this->loadData();
    }

    public function acknowledgeAlert(int $alertId): void
    {
        DB::table('admin_alerts')
            ->where('id', $alertId)
            ->update([
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
                'updated_at' => now(),
            ]);

        $this->loadData();
    }

    private function loadData(): void
    {
        $useCase = new ComputeAnalyticsUseCase();
        $this->analytics = $useCase->execute(
            $this->dateFrom . ' 00:00:00',
            $this->dateTo . ' 23:59:59',
        );

        $this->alerts = DB::table('admin_alerts')
            ->whereNull('acknowledged_by')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => (array) $a)
            ->toArray();

        $this->totalOrders = DB::table('orders')->count();
        $this->activeOrders = DB::table('orders')
            ->whereIn('status', ['pending_confirmation', 'in_preparation', 'served'])
            ->count();
    }

    public function pinTrendingTerm(): void
    {
        $this->trendingError = null;
        $this->trendingMessage = null;

        $term = trim($this->newTrendingTerm);
        if (empty($term)) {
            $this->trendingError = 'Term cannot be empty.';
            return;
        }

        // Enforce max 20 pinned trending terms per location
        $query = DB::table('trending_searches');
        if ($this->trendingLocationId !== null) {
            $query->where('location_id', $this->trendingLocationId);
        } else {
            $query->whereNull('location_id');
        }
        $currentCount = $query->count();

        if ($currentCount >= self::MAX_PINNED_TRENDING_PER_LOCATION) {
            $this->trendingError = 'Cannot pin more than ' . self::MAX_PINNED_TRENDING_PER_LOCATION . ' trending terms per location.';
            return;
        }

        // Determine sort_order
        $maxSort = DB::table('trending_searches')
            ->when($this->trendingLocationId !== null, fn ($q) => $q->where('location_id', $this->trendingLocationId))
            ->when($this->trendingLocationId === null, fn ($q) => $q->whereNull('location_id'))
            ->max('sort_order') ?? 0;

        DB::table('trending_searches')->insert([
            'term' => $term,
            'sort_order' => $maxSort + 1,
            'pinned_by' => auth()->id(),
            'location_id' => $this->trendingLocationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->newTrendingTerm = '';
        $this->trendingMessage = "Trending term \"{$term}\" pinned successfully.";
        $this->loadTrendingTerms();
    }

    public function removeTrendingTerm(int $id): void
    {
        DB::table('trending_searches')->where('id', $id)->delete();
        $this->trendingMessage = 'Trending term removed.';
        $this->loadTrendingTerms();
    }

    public function updatedTrendingLocationId(): void
    {
        $this->loadTrendingTerms();
    }

    private function loadTrendingTerms(): void
    {
        $query = DB::table('trending_searches')
            ->orderBy('sort_order');

        if ($this->trendingLocationId !== null) {
            $query->where('location_id', $this->trendingLocationId);
        } else {
            $query->whereNull('location_id');
        }

        $this->trendingTerms = $query->limit(self::MAX_PINNED_TRENDING_PER_LOCATION)
            ->get()
            ->map(fn ($t) => (array) $t)
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
