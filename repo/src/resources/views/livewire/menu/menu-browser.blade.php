<div class="flex gap-6">
    {{-- Sidebar: Filters --}}
    <aside class="w-60 shrink-0 space-y-6">
        {{-- Search --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Search</label>
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search menu..."
                class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            >
        </div>

        {{-- Categories --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <select
                wire:model.live="categoryId"
                class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-3 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            >
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Price Range --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Price Range</label>
            <div class="mt-1 flex gap-2">
                <input
                    wire:model.live.debounce.500ms="priceMin"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Min"
                    class="block h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                >
                <input
                    wire:model.live.debounce.500ms="priceMax"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Max"
                    class="block h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                >
            </div>
        </div>

        {{-- Attribute Filters --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Dietary Filters</label>
            <div class="mt-2 space-y-2">
                @php
                    $filterOptions = [
                        'nuts' => 'No Nuts',
                        'gluten' => 'Gluten-Free',
                        'dairy' => 'No Dairy',
                        'shellfish' => 'No Shellfish',
                        'vegan' => 'Vegan Only',
                    ];
                @endphp
                @foreach ($filterOptions as $filterKey => $filterLabel)
                    <label class="flex min-h-11 cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            wire:click="toggleAllergen('{{ $filterKey }}')"
                            @checked(in_array($filterKey, $allergenExclusions))
                            class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm text-gray-700">{{ $filterLabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Spicy Level --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Max Spicy Level</label>
            <select
                wire:model.live="maxSpicyLevel"
                class="mt-1 block h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            >
                <option value="">Any</option>
                <option value="0">Not Spicy</option>
                <option value="1">Mild or less</option>
                <option value="2">Medium or less</option>
                <option value="3">Hot or less</option>
            </select>
        </div>

        {{-- Sort --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Sort By</label>
            <select
                wire:model.live="sort"
                class="mt-1 block h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            >
                <option value="relevance">Relevance</option>
                <option value="newest">Newest</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>

        {{-- Clear Filters --}}
        <button
            wire:click="clearFilters"
            class="min-h-11 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Clear All Filters
        </button>
    </aside>

    {{-- Main Content --}}
    <div class="flex-1">
        {{-- Blocked Query Message --}}
        @if ($blocked)
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="font-medium text-red-800">{{ $blockMessage }}</p>
                @if ($suggestion)
                    <p class="mt-2 text-sm text-red-600">{{ $suggestion }}</p>
                @endif
            </div>
        @endif

        {{-- Trending Terms --}}
        @if (empty($search) && !empty($trending))
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-500">Trending Searches</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($trending as $term)
                        <button
                            wire:click="selectTrending('{{ $term }}')"
                            class="min-h-11 rounded-full bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:bg-blue-200"
                        >
                            {{ $term }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent Searches (browser localStorage) --}}
        @if (empty($search))
            <div class="mb-6" x-data="{
                recentSearches: JSON.parse(localStorage.getItem('harborbite_recent_searches') || '[]'),
                selectRecent(term) {
                    $wire.set('search', term);
                },
                clearRecent() {
                    this.recentSearches = [];
                    localStorage.removeItem('harborbite_recent_searches');
                }
            }" x-init="
                Livewire.on('search-performed', (data) => {
                    if (data.keyword && data.keyword.trim()) {
                        let searches = JSON.parse(localStorage.getItem('harborbite_recent_searches') || '[]');
                        searches = [data.keyword, ...searches.filter(s => s !== data.keyword)].slice(0, 10);
                        localStorage.setItem('harborbite_recent_searches', JSON.stringify(searches));
                        recentSearches = searches;
                    }
                });
            ">
                <template x-if="recentSearches.length > 0">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-500">Recent Searches</h3>
                            <button @click="clearRecent" class="text-xs text-gray-400 hover:text-gray-600">Clear</button>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="term in recentSearches" :key="term">
                                <button
                                    @click="selectRecent(term)"
                                    x-text="term"
                                    class="min-h-9 rounded-full bg-gray-100 px-3 py-1.5 text-sm text-gray-600 transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 active:bg-gray-300"
                                ></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        @endif

        {{-- Results Count --}}
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                @if ($total > 0)
                    Showing {{ count($items) }} of {{ $total }} items
                @else
                    No items found
                @endif
            </p>
            <div wire:loading class="text-sm text-blue-600">
                <svg class="inline h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Searching...
            </div>
        </div>

        {{-- Menu Grid --}}
        @if ($total > 0)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <div class="flex flex-col rounded-xl bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
                        <div class="flex-1">
                            <div class="flex items-start justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $item['name'] }}</h3>
                                <span class="ml-2 whitespace-nowrap text-lg font-bold text-blue-700">${{ number_format((float) $item['price'], 2) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ $item['category_name'] }}</p>
                            @if ($item['description'])
                                <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $item['description'] }}</p>
                            @endif

                            {{-- Attribute badges --}}
                            @php $attrs = is_array($item['attributes']) ? $item['attributes'] : []; @endphp
                            <div class="mt-3 flex flex-wrap gap-1">
                                @if (!empty($attrs['gluten_free']))
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Gluten-Free</span>
                                @endif
                                @if (!empty($attrs['spicy_level']) && $attrs['spicy_level'] > 0)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                        Spicy {{ str_repeat('🌶', min($attrs['spicy_level'], 3)) }}
                                    </span>
                                @endif
                                @if (!empty($attrs['contains_nuts']))
                                    <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">Contains Nuts</span>
                                @endif
                            </div>
                        </div>

                        <button
                            wire:click="$dispatch('add-to-cart', { itemId: {{ $item['id'] }} })"
                            class="mt-4 flex h-11 w-full items-center justify-center rounded-lg bg-blue-700 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 active:bg-blue-800"
                        >
                            Add to Cart
                        </button>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($total > 20)
                <div class="mt-6 flex items-center justify-center gap-4">
                    <button
                        wire:click="prevPage"
                        @disabled($page <= 1)
                        class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-gray-500">Page {{ $page }}</span>
                    <button
                        wire:click="nextPage"
                        @disabled(count($items) < 20)
                        class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            @endif
        @elseif (!$blocked)
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-8 text-center">
                <p class="text-gray-500">No items match your filters. Try adjusting your search criteria.</p>
            </div>
        @endif
    </div>
</div>
