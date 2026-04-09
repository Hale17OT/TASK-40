<div>
    @if($message)
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-800">{{ $message }}</div>
    @endif
    @if($error)
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    {{-- Filter --}}
    <div class="mb-4 flex items-center gap-4">
        <label class="text-sm font-medium text-gray-700">Status:</label>
        <select wire:model.live="filterStatus" class="h-11 rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="open">Open</option>
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
            <option value="all">All</option>
        </select>
        <span class="text-sm text-gray-500">{{ count($tickets) }} ticket(s)</span>
    </div>

    {{-- Resolve Modal --}}
    @if($resolvingTicketId)
        <div class="mb-6 rounded-lg border-2 border-blue-300 bg-blue-50 p-4 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold text-blue-900">Resolve Ticket #{{ $resolvingTicketId }}</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason Code</label>
                    <select wire:model="reasonCode" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($reasonCodes as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Receipt Reference (optional)</label>
                    <input type="text" wire:model="receiptReference" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. POS-20260406-0042" />
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button wire:click="resolveTicket" class="min-h-11 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 active:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50">Confirm Resolution</button>
                <button wire:click="cancelResolve" class="min-h-11 rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-400">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Ticket List --}}
    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Payment</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">#{{ $ticket['id'] }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                @if($ticket['type'] === 'paid_not_settled') bg-red-100 text-red-800
                                @elseif($ticket['type'] === 'expired_intent') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-700
                                @endif
                            ">{{ str_replace('_', ' ', $ticket['type']) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            Order #{{ $ticket['order_id'] }}
                            @if($ticket['order_status'])
                                <span class="ml-1 text-xs text-gray-500">({{ $ticket['order_status'] }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($ticket['payment_amount'])
                                ${{ number_format((float)$ticket['payment_amount'], 2) }}
                                <span class="ml-1 text-xs text-gray-500">{{ $ticket['payment_status'] ?? '' }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                @if($ticket['status'] === 'open') bg-red-100 text-red-800
                                @elseif($ticket['status'] === 'resolved') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-600
                                @endif
                            ">{{ ucfirst($ticket['status']) }}</span>
                            @if($ticket['resolution_reason_code'])
                                <div class="mt-1 text-xs text-gray-500">{{ $reasonCodes[$ticket['resolution_reason_code']] ?? $ticket['resolution_reason_code'] }}</div>
                            @endif
                            @if($ticket['resolver_name'])
                                <div class="text-xs text-gray-400">by {{ $ticket['resolver_name'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $ticket['created_at'] }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            @if($ticket['status'] === 'open')
                                <button wire:click="openResolve({{ $ticket['id'] }})" class="min-h-11 rounded px-2 py-1 text-green-600 hover:bg-green-50 hover:underline focus:outline-none focus:ring-2 focus:ring-green-200">Settle</button>
                                <button wire:click="dismissTicket({{ $ticket['id'] }})" wire:confirm="Dismiss this ticket?" class="ml-2 min-h-11 rounded px-2 py-1 text-gray-500 hover:bg-gray-100 hover:underline focus:outline-none focus:ring-2 focus:ring-gray-200">Dismiss</button>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No incident tickets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
