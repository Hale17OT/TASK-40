<div>
    @if($message)
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-800">{{ $message }}</div>
    @endif
    @if($error)
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    {{-- Promotion Form --}}
    <div class="mb-6 rounded-lg border bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-lg font-semibold">{{ $editingId ? 'Edit Promotion' : 'Add Promotion' }}</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model="name" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select wire:model="type" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($availableTypes as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Exclusion Group</label>
                <input type="text" wire:model="exclusionGroup" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. combo_deals" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Starts At</label>
                <input type="datetime-local" wire:model="startsAt" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Ends At</label>
                <input type="datetime-local" wire:model="endsAt" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="isActive" class="rounded" /> Active
                </label>
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700">Rules (JSON)</label>
            <textarea wire:model="rulesJson" rows="3" class="mt-1 w-full rounded border-gray-300 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder='{"threshold": 30, "percentage": 10}'></textarea>
            <p class="mt-1 text-xs text-gray-500">Examples: {"threshold": 30, "percentage": 10} or {"target_skus": ["SKU1", "SKU2"]}</p>
        </div>
        <div class="mt-4 flex gap-2">
            <button wire:click="save" class="min-h-11 rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $editingId ? 'Update' : 'Create' }}</button>
            @if($editingId)
                <button wire:click="resetForm" class="min-h-11 rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-400">Cancel</button>
            @endif
        </div>
    </div>

    {{-- Promotion List --}}
    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Exclusion Group</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Window</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Active</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($promotions as $promo)
                    <tr class="{{ !$promo['is_active'] ? 'bg-gray-50 opacity-60' : '' }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $promo['name'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $availableTypes[$promo['type']] ?? $promo['type'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $promo['exclusion_group'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $promo['starts_at'] }}<br>to {{ $promo['ends_at'] }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $promo['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $promo['is_active'] ? 'Yes' : 'No' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <button wire:click="edit({{ $promo['id'] }})" class="min-h-11 rounded px-2 py-1 text-blue-600 hover:bg-blue-50 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">Edit</button>
                            <button wire:click="toggleActive({{ $promo['id'] }})" class="ml-2 min-h-11 rounded px-2 py-1 {{ $promo['is_active'] ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50' }} hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">
                                {{ $promo['is_active'] ? 'Deactivate' : 'Activate' }}
                            </button>
                            <button wire:click="delete({{ $promo['id'] }})" wire:confirm="Delete this promotion?" class="ml-2 min-h-11 rounded px-2 py-1 text-red-600 hover:bg-red-50 hover:underline focus:outline-none focus:ring-2 focus:ring-red-200">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No promotions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
