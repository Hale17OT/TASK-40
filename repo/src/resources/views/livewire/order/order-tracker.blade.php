<div wire:poll.5s="loadOrder">
    {{-- Loading indicator for poll refresh --}}
    <div wire:loading wire:target="loadOrder" class="mb-4 flex items-center justify-center gap-2 text-sm text-blue-600">
        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        Refreshing...
    </div>

    @if (!$order)
        <div class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">Order not found.</p>
        </div>
    @else
        <div class="space-y-4">
            {{-- Status Banner --}}
            @php
                $statusColor = match($order['status']) {
                    'pending_confirmation' => 'bg-yellow-100 border-yellow-300 text-yellow-800',
                    'in_preparation' => 'bg-blue-100 border-blue-300 text-blue-800',
                    'served' => 'bg-green-100 border-green-300 text-green-800',
                    'settled' => 'bg-gray-100 border-gray-300 text-gray-800',
                    'canceled' => 'bg-red-100 border-red-300 text-red-800',
                    default => 'bg-gray-100 border-gray-300 text-gray-600',
                };
            @endphp
            <div class="rounded-xl border-2 p-6 text-center {{ $statusColor }}">
                <h2 class="text-2xl font-bold">{{ \App\Domain\Order\OrderStatus::from($order['status'])->label() }}</h2>
                <p class="mt-1 text-sm opacity-75">Order {{ $order['order_number'] }}</p>
            </div>

            {{-- Order Items --}}
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="font-bold text-gray-900">Items</h3>
                <div class="mt-3 divide-y">
                    @foreach ($items as $item)
                        <div class="flex justify-between py-2">
                            <div>
                                <span class="font-medium">{{ $item['item_name'] }}</span>
                                <span class="text-sm text-gray-500"> x{{ $item['quantity'] }}</span>
                                @if ($item['locked_at'])
                                    <span class="ml-1 text-xs text-blue-600">Preparing</span>
                                @endif
                            </div>
                            <span class="font-medium">${{ number_format((float) $item['unit_price'] * $item['quantity'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 border-t pt-3">
                    <div class="flex justify-between font-bold">
                        <span>Total</span>
                        <span>${{ number_format((float) $order['total'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
