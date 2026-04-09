<div>
    @if ($orderPlaced)
        <div class="rounded-xl bg-green-50 p-8 text-center">
            <svg class="mx-auto h-16 w-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-xl font-bold text-green-800">Order Placed!</h3>
            <p class="mt-1 text-green-600">Order #{{ $orderNumber }}</p>
            <a href="/order/{{ $trackingToken }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 active:bg-green-700">
                Track Your Order
            </a>
        </div>
    @elseif (!$cartSummary)
        <div class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">Your cart is empty. Add items before checking out.</p>
            <a href="/" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800">
                Browse Menu
            </a>
        </div>
    @else
        <div class="space-y-4">
            @if ($errorMessage)
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
            @endif

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Review Your Order</h3>
                <div class="mt-4 divide-y">
                    @foreach ($cartSummary['items'] as $item)
                        <div class="flex justify-between py-2">
                            <div>
                                <span class="font-medium">{{ $item['name'] }}</span>
                                <span class="text-sm text-gray-500"> x{{ $item['quantity'] }}</span>
                            </div>
                            <span>${{ number_format($item['line_total'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2 border-t pt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium">${{ number_format($cartSummary['subtotal'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Estimated Tax</span>
                        <span class="font-medium">${{ number_format($cartSummary['estimated_tax'] ?? 0, 2) }}</span>
                    </div>

                    @if ($appliedPromotion)
                        <div class="flex justify-between rounded-lg bg-green-50 px-3 py-2 text-sm">
                            <div>
                                <span class="font-medium text-green-800">{{ $appliedPromotion['name'] }}</span>
                                <p class="text-xs text-green-600">{{ $appliedPromotion['description'] }}</p>
                                @if (!empty($appliedPromotion['time_window']))
                                    <p class="text-xs text-green-500">Valid: {{ $appliedPromotion['time_window'] }}</p>
                                @endif
                            </div>
                            <span class="font-bold text-green-700">-${{ number_format($appliedPromotion['discount_amount'], 2) }}</span>
                        </div>
                    @else
                        <div class="flex justify-between text-sm text-gray-400">
                            <span>Discount</span>
                            <span>None applied</span>
                        </div>
                    @endif

                    <div class="flex justify-between border-t pt-2 text-lg font-bold">
                        <span>Estimated Total</span>
                        <span class="text-blue-700">${{ number_format($cartSummary['total_after_discount'], 2) }}</span>
                    </div>
                </div>
            </div>

            <button
                wire:click="placeOrder"
                wire:loading.attr="disabled"
                class="flex h-14 w-full items-center justify-center rounded-xl bg-blue-700 text-lg font-bold text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove>Place Order</span>
                <span wire:loading>
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </span>
            </button>
        </div>

        {{-- Rapid Re-pricing CAPTCHA Challenge --}}
        @if ($requiresRepricingCaptcha && !$repricingCaptchaPassed)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-gray-900">Security Check</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Frequent price changes detected. Please verify you are not a bot.
                    </p>

                    @if ($errorMessage)
                        <div class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errorMessage }}</div>
                    @endif

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">{{ $captchaQuestion }}</label>
                        <input
                            wire:model="captchaAnswer"
                            type="text"
                            inputmode="numeric"
                            placeholder="Your answer"
                            class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-center text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>

                    <button wire:click="verifyRepricingCaptcha" wire:loading.attr="disabled" class="mt-4 flex h-12 w-full items-center justify-center rounded-lg bg-blue-700 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Verify
                    </button>
                </div>
            </div>
        @endif

        {{-- Manager PIN Modal for Discount Override > $20 --}}
        @if ($requiresDiscountOverridePin)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-gray-900">Manager Authorization Required</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        A discount over $20.00 requires manager PIN approval.
                    </p>

                    @if ($errorMessage)
                        <div class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errorMessage }}</div>
                    @endif

                    <div class="mt-4">
                        <input
                            wire:model="discountOverridePin"
                            type="password"
                            placeholder="Manager PIN"
                            class="block h-12 w-full rounded-lg border border-gray-300 px-4 text-center text-lg tracking-widest focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>

                    <div class="mt-4 flex gap-3">
                        <button wire:click="cancelDiscountOverride" class="min-h-11 flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200">
                            Cancel
                        </button>
                        <button wire:click="approveDiscountOverride" wire:loading.attr="disabled" class="min-h-11 flex-1 rounded-lg bg-blue-700 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Authorize
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
