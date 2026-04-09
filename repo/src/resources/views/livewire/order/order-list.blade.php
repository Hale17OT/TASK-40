<div>
    {{-- Status Filter --}}
    <div class="mb-6 flex flex-wrap gap-2">
        <button wire:click="$set('statusFilter', '')" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 {{ !$statusFilter ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 active:bg-gray-400' }}">
            Active Orders
        </button>
        @foreach (['pending_confirmation' => 'Pending', 'in_preparation' => 'Preparing', 'served' => 'Served', 'settled' => 'Settled', 'canceled' => 'Canceled'] as $status => $label)
            <button wire:click="$set('statusFilter', '{{ $status }}')" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 {{ $statusFilter === $status ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 active:bg-gray-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Version Conflict Banner --}}
    @if ($showVersionConflict)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div class="flex-1">
                    <h4 class="font-bold text-amber-800">Version Conflict Detected</h4>
                    <p class="mt-1 text-sm text-amber-700">
                        This order was modified by another user while you were viewing it.
                        @if ($conflictCurrentStatus)
                            The order is now in <strong>{{ \App\Domain\Order\OrderStatus::from($conflictCurrentStatus)->label() }}</strong> status.
                        @endif
                        The order list has been refreshed with the latest data.
                    </p>
                    <button wire:click="dismissConflict" class="mt-2 min-h-11 rounded-lg bg-amber-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 active:bg-amber-700">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Messages --}}
    @if ($errorMessage)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if ($successMessage)
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700">{{ $successMessage }}</div>
    @endif

    {{-- Loading indicator for poll/transitions --}}
    <div wire:loading class="mb-4 flex items-center gap-2 text-sm text-blue-600">
        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        Updating orders...
    </div>

    {{-- Orders Grid --}}
    @if (empty($orders))
        <div class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">No orders found.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3" wire:poll.5s="loadOrders">
            @foreach ($orders as $order)
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-gray-900">{{ $order['order_number'] }}</h4>
                        @php
                            $badgeClass = match($order['status']) {
                                'pending_confirmation' => 'bg-yellow-100 text-yellow-800',
                                'in_preparation' => 'bg-blue-100 text-blue-800',
                                'served' => 'bg-green-100 text-green-800',
                                'settled' => 'bg-gray-100 text-gray-800',
                                'canceled' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $badgeClass }}">
                            {{ \App\Domain\Order\OrderStatus::from($order['status'])->label() }}
                        </span>
                    </div>

                    <div class="mt-2 text-sm text-gray-500">
                        <span>Total: <strong>${{ number_format((float) $order['total'], 2) }}</strong></span>
                        <span class="ml-2 text-xs text-gray-400">v{{ $order['version'] }}</span>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($order['status'] === 'pending_confirmation')
                            @if (auth()->user()->isRole('cashier', 'manager', 'administrator'))
                                <button wire:click="transitionOrder({{ $order['id'] }}, 'in_preparation')" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    Confirm
                                </button>
                            @endif
                            <button wire:click="transitionOrder({{ $order['id'] }}, 'canceled')" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-red-100 px-4 py-2 text-xs font-medium text-red-700 transition-colors hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 active:bg-red-300 disabled:cursor-not-allowed disabled:opacity-50">
                                Cancel
                            </button>
                        @elseif ($order['status'] === 'in_preparation')
                            @if (auth()->user()->isRole('kitchen', 'manager', 'administrator'))
                                <button wire:click="transitionOrder({{ $order['id'] }}, 'served')" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-green-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-300 focus:ring-offset-2 active:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    Mark Served
                                </button>
                            @endif
                            @if (auth()->user()->isRole('manager', 'administrator'))
                                <button wire:click="transitionOrder({{ $order['id'] }}, 'canceled')" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-red-100 px-4 py-2 text-xs font-medium text-red-700 transition-colors hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 active:bg-red-300 disabled:cursor-not-allowed disabled:opacity-50">
                                    Cancel (PIN)
                                </button>
                            @endif
                        @elseif ($order['status'] === 'served')
                            {{-- Settlement requires payment confirmation workflow (no direct button) --}}
                            <span class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs text-gray-500">
                                Awaiting Payment
                            </span>
                        @endif

                        {{-- Discount Override (manager/admin only, any non-terminal status) --}}
                        @if (auth()->user()->isRole('manager', 'administrator') && !in_array($order['status'], ['settled', 'canceled']))
                            <button wire:click="openDiscountOverride({{ $order['id'] }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-yellow-100 px-4 py-2 text-xs font-medium text-yellow-800 transition-colors hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:ring-offset-2 active:bg-yellow-300 disabled:cursor-not-allowed disabled:opacity-50">
                                Apply Discount
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Manager PIN Modal --}}
    @if ($showPinModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Manager Authorization Required</h3>
                <p class="mt-1 text-sm text-gray-500">Enter your manager PIN to proceed.</p>

                <div class="mt-4 space-y-3">
                    <input
                        wire:model="managerPin"
                        type="password"
                        placeholder="Manager PIN"
                        class="block h-12 w-full rounded-lg border border-gray-300 px-4 text-center text-lg tracking-widest focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                    <textarea
                        wire:model="cancelReason"
                        placeholder="Reason (optional)"
                        rows="2"
                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    ></textarea>
                </div>

                <div class="mt-4 flex gap-3">
                    <button wire:click="cancelPinModal" class="min-h-11 flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200">
                        Cancel
                    </button>
                    <button wire:click="confirmWithPin" wire:loading.attr="disabled" class="min-h-11 flex-1 rounded-lg bg-blue-700 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Authorize
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Discount Override Modal --}}
    @if ($showDiscountModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Apply Manual Discount</h3>
                <p class="mt-1 text-sm text-gray-500">Discounts over $20 require manager PIN.</p>

                @if ($errorMessage)
                    <div class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errorMessage }}</div>
                @endif

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-sm text-gray-600">Discount Amount ($)</label>
                        <input
                            wire:model="discountAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="mt-1 block h-11 w-full rounded-lg border border-gray-300 px-4 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Manager PIN (if > $20)</label>
                        <input
                            wire:model="discountPin"
                            type="password"
                            placeholder="Manager PIN"
                            class="mt-1 block h-11 w-full rounded-lg border border-gray-300 px-4 text-center tracking-widest focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Reason</label>
                        <input
                            wire:model="discountReason"
                            type="text"
                            placeholder="Reason (optional)"
                            class="mt-1 block h-11 w-full rounded-lg border border-gray-300 px-4 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                    </div>
                </div>

                <div class="mt-4 flex gap-3">
                    <button wire:click="cancelDiscountModal" class="min-h-11 flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-200">
                        Cancel
                    </button>
                    <button wire:click="applyDiscountOverride" wire:loading.attr="disabled" class="min-h-11 flex-1 rounded-lg bg-yellow-500 py-2.5 text-sm font-medium text-white transition-colors hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-2 active:bg-yellow-600 disabled:cursor-not-allowed disabled:opacity-50">
                        Apply Discount
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
