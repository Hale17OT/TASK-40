<div>
    @if (!$hasItems)
        <div class="flex flex-col items-center justify-center rounded-xl bg-white p-12 shadow-sm">
            <svg class="h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">Your cart is empty</h3>
            <p class="mt-1 text-sm text-gray-500">Browse our menu and add some items!</p>
            <a href="/" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800">
                Browse Menu
            </a>
        </div>
    @else
        <div class="space-y-4">
            {{-- Cart Items --}}
            @foreach ($cartItems as $item)
                <div class="rounded-xl bg-white p-4 shadow-sm {{ $item['price_changed'] ? 'ring-2 ring-yellow-400' : '' }}">
                    @if ($item['price_changed'])
                        <div class="mb-2 rounded-lg bg-yellow-50 px-3 py-1.5 text-sm text-yellow-800">
                            Price updated: ${{ number_format($priceChanges[$item['id']]['old'], 2) }} &rarr; ${{ number_format($priceChanges[$item['id']]['new'], 2) }}
                        </div>
                    @endif

                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900">{{ $item['name'] }}</h4>
                            <p class="text-sm text-gray-500">SKU: {{ $item['sku'] }}</p>
                        </div>
                        <span class="text-lg font-bold text-blue-700">${{ number_format($item['line_total'], 2) }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        {{-- Quantity --}}
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-500">Qty:</label>
                            <button
                                wire:click="updateQuantity({{ $item['id'] }}, {{ max(1, $item['quantity'] - 1) }})"
                                wire:loading.attr="disabled"
                                class="flex h-11 w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                                @disabled($item['quantity'] <= 1)
                            >-</button>
                            <span class="w-8 text-center font-medium">
                                <span wire:loading.remove wire:target="updateQuantity({{ $item['id'] }}, {{ max(1, $item['quantity'] - 1) }}), updateQuantity({{ $item['id'] }}, {{ $item['quantity'] + 1 }})">{{ $item['quantity'] }}</span>
                                <svg wire:loading wire:target="updateQuantity({{ $item['id'] }}, {{ max(1, $item['quantity'] - 1) }}), updateQuantity({{ $item['id'] }}, {{ $item['quantity'] + 1 }})" class="mx-auto h-4 w-4 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                            <button
                                wire:click="updateQuantity({{ $item['id'] }}, {{ $item['quantity'] + 1 }})"
                                wire:loading.attr="disabled"
                                class="flex h-11 w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                            >+</button>
                        </div>

                        <span class="text-sm text-gray-400">@ ${{ number_format($item['unit_price'], 2) }} each</span>

                        {{-- Remove --}}
                        <button
                            wire:click="removeItem({{ $item['id'] }})"
                            wire:loading.attr="disabled"
                            class="ml-auto min-h-11 rounded-lg px-3 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-200 active:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Remove
                        </button>
                    </div>

                    {{-- Flavor Preference --}}
                    <div class="mt-3">
                        <input
                            type="text"
                            wire:change="updateFlavor({{ $item['id'] }}, $event.target.value)"
                            value="{{ $item['flavor_preference'] }}"
                            placeholder="Flavor preference (optional)"
                            class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>

                    {{-- Note --}}
                    <div class="mt-2">
                        <div class="relative">
                            <textarea
                                wire:change="updateNote({{ $item['id'] }}, $event.target.value)"
                                maxlength="140"
                                rows="2"
                                placeholder="Special instructions (max 140 chars)"
                                class="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                            >{{ $item['note'] }}</textarea>
                            <span class="absolute bottom-1 right-2 text-xs text-gray-400">
                                {{ mb_strlen($item['note'] ?? '') }}/140
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Summary --}}
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Order Summary</h3>

                <div class="mt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium">${{ number_format($subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Estimated Tax</span>
                        <span class="font-medium">${{ number_format($tax, 2) }}</span>
                    </div>

                    {{-- Tax breakdown --}}
                    @if (!empty($taxBreakdown))
                        <div class="ml-4 space-y-1">
                            @foreach ($taxBreakdown as $tb)
                                <div class="flex justify-between text-xs text-gray-400">
                                    <span>{{ $tb['name'] }} ({{ number_format($tb['rate'] * 100, 2) }}%)</span>
                                    <span>${{ number_format($tb['tax_amount'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="border-t border-gray-200 pt-2">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Estimated Total</span>
                            <span class="text-blue-700">${{ number_format($total, 2) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Promotions applied at checkout. Final total may include discounts.</p>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        wire:click="clearCart"
                        wire:loading.attr="disabled"
                        wire:confirm="Are you sure you want to clear your cart?"
                        class="min-h-11 flex-1 rounded-lg border border-gray-300 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Clear Cart
                    </button>
                    <a
                        href="/checkout"
                        class="flex min-h-11 flex-1 items-center justify-center rounded-lg bg-blue-700 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800"
                    >
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
