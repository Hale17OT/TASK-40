<div>
    {{-- Alert Banners --}}
    @foreach ($alerts as $alert)
        <div class="mb-4 flex items-center justify-between rounded-lg p-4 {{ $alert['severity'] === 'critical' ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200' }}">
            <div>
                <span class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $alert['severity'] === 'critical' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' }}">
                    {{ $alert['severity'] }}
                </span>
                <span class="ml-2 text-sm font-medium text-gray-900">{{ $alert['message'] }}</span>
            </div>
            <button wire:click="acknowledgeAlert({{ $alert['id'] }})" class="min-h-11 rounded-lg px-3 text-sm text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 active:bg-gray-200">Dismiss</button>
        </div>
    @endforeach

    {{-- Date Range --}}
    <div class="mb-6 flex items-center gap-4">
        <div>
            <label class="text-sm text-gray-500">From</label>
            <input wire:model.live="dateFrom" type="date" class="ml-1 h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div>
            <label class="text-sm text-gray-500">To</label>
            <input wire:model.live="dateTo" type="date" class="ml-1 h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div wire:loading wire:target="dateFrom, dateTo" class="flex items-center gap-2 text-sm text-blue-600">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Loading...
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="min-h-24 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Total GMV</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">${{ number_format($analytics['total_gmv'] ?? 0, 2) }}</p>
        </div>
        <div class="min-h-24 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Total Sessions</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $analytics['total_sessions'] ?? 0 }}</p>
        </div>
        <div class="min-h-24 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Conversion Rate</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $analytics['conversion_rate'] ?? 0 }}%</p>
        </div>
        <div class="min-h-24 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Retention Rate</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $analytics['retention_rate'] ?? 0 }}%</p>
            <p class="mt-0.5 text-xs text-gray-400">{{ $analytics['returning_sessions'] ?? 0 }} returning sessions</p>
        </div>
        <div class="min-h-24 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Active Orders</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ $activeOrders }}</p>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="mb-8 rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Conversion Funnel</h3>
        <div class="mt-4 space-y-3">
            @php
                $funnelSteps = [
                    'Page Views' => $analytics['funnel']['page_views'] ?? 0,
                    'Add to Cart' => $analytics['funnel']['add_to_cart'] ?? 0,
                    'Checkout Started' => $analytics['funnel']['checkout_started'] ?? 0,
                    'Orders Placed' => $analytics['funnel']['orders_placed'] ?? 0,
                    'Orders Settled' => $analytics['funnel']['orders_settled'] ?? 0,
                ];
                $maxVal = max(1, max($funnelSteps));
            @endphp
            @foreach ($funnelSteps as $step => $count)
                <div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $step }}</span>
                        <span class="font-medium">{{ $count }}</span>
                    </div>
                    <div class="mt-1 h-3 w-full rounded-full bg-gray-100">
                        <div class="h-3 rounded-full bg-blue-600 transition-all" style="width: {{ round($count / $maxVal * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Trending Terms Management --}}
    <div class="mb-8 rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Pinned Trending Terms</h3>
        <p class="text-sm text-gray-500">Admins can pin up to 20 trending terms per location.</p>

        @if ($trendingError)
            <div class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $trendingError }}</div>
        @endif
        @if ($trendingMessage)
            <div class="mt-2 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ $trendingMessage }}</div>
        @endif

        <div class="mt-4 flex items-end gap-3">
            <div>
                <label class="text-sm text-gray-500">Location ID (optional)</label>
                <input wire:model.live="trendingLocationId" type="number" placeholder="All locations" class="ml-1 h-11 w-32 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>
            <div class="flex-1">
                <label class="text-sm text-gray-500">Term</label>
                <input wire:model="newTrendingTerm" type="text" placeholder="e.g. burger" class="ml-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>
            <button wire:click="pinTrendingTerm" class="h-11 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                Pin Term
            </button>
        </div>

        <div class="mt-4">
            <p class="text-xs text-gray-400">{{ count($trendingTerms) }} / 20 terms pinned for {{ $trendingLocationId ? 'location #' . $trendingLocationId : 'all locations' }}</p>
            @if (!empty($trendingTerms))
                <div class="mt-2 space-y-1">
                    @foreach ($trendingTerms as $term)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                            <span class="text-sm font-medium text-gray-800">{{ $term['term'] }}</span>
                            <button wire:click="removeTrendingTerm({{ $term['id'] }})" class="min-h-11 rounded-lg px-3 text-xs text-red-600 transition-colors hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-200 active:bg-red-100">Remove</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-sm text-gray-400">No trending terms pinned.</p>
            @endif
        </div>
    </div>

    {{-- Daily GMV --}}
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Daily GMV</h3>
        @if (empty($analytics['gmv']))
            <p class="mt-4 text-sm text-gray-500">No revenue data for this period.</p>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($analytics['gmv'] as $day)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $day['day'] }}</span>
                        <span class="font-medium">${{ number_format((float) ($day['total'] ?? 0), 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
