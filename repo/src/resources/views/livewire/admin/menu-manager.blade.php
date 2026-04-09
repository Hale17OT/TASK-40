<div>
    {{-- Feedback --}}
    @if($message)
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-800">{{ $message }}</div>
    @endif
    @if($error)
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 flex gap-4 border-b">
        <button wire:click="$set('tab', 'items')" class="min-h-11 px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-200 {{ $tab === 'items' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">Menu Items</button>
        <button wire:click="$set('tab', 'categories')" class="min-h-11 px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-200 {{ $tab === 'categories' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">Categories</button>
    </div>

    @if($tab === 'categories')
        {{-- Category Form --}}
        <div class="mb-6 rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold">{{ $editingCategoryId ? 'Edit Category' : 'Add Category' }}</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" wire:model="catName" class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sort Order</label>
                    <input type="number" wire:model="catSortOrder" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>
                <div class="flex items-end gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="catIsActive" class="rounded" /> Active
                    </label>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button wire:click="saveCategory" class="min-h-11 rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $editingCategoryId ? 'Update' : 'Create' }}</button>
                @if($editingCategoryId)
                    <button wire:click="resetCategoryForm" class="min-h-11 rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-400">Cancel</button>
                @endif
            </div>
        </div>

        {{-- Category List --}}
        <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Sort</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Active</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($categories as $cat)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $cat['name'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $cat['sort_order'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $cat['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $cat['is_active'] ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="editCategory({{ $cat['id'] }})" class="min-h-11 rounded px-2 py-1 text-blue-600 hover:bg-blue-50 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">Edit</button>
                                <button wire:click="deleteCategory({{ $cat['id'] }})" wire:confirm="Delete this category?" class="ml-2 min-h-11 rounded px-2 py-1 text-red-600 hover:bg-red-50 hover:underline focus:outline-none focus:ring-2 focus:ring-red-200">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        {{-- Item Form --}}
        <div class="mb-6 rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold">{{ $editingItemId ? 'Edit Item' : 'Add Item' }}</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">SKU</label>
                    <input type="text" wire:model="itemSku" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" wire:model="itemName" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <select wire:model="itemCategoryId" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- Select --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Price</label>
                    <input type="text" wire:model="itemPrice" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="0.00" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tax Category</label>
                    <select wire:model="itemTaxCategory" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="hot_prepared">Hot Prepared</option>
                        <option value="cold_prepared">Cold Prepared</option>
                        <option value="beverage">Beverage</option>
                        <option value="packaged">Packaged</option>
                        <option value="exempt">Exempt</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="itemIsActive" class="rounded" /> Active
                    </label>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea wire:model="itemDescription" rows="2" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
            <div class="mt-4 flex gap-2">
                <button wire:click="saveItem" class="min-h-11 rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $editingItemId ? 'Update' : 'Create' }}</button>
                @if($editingItemId)
                    <button wire:click="resetItemForm" class="min-h-11 rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-400">Cancel</button>
                @endif
            </div>
        </div>

        {{-- Filter --}}
        <div class="mb-4">
            <label class="text-sm font-medium text-gray-700">Filter by Category:</label>
            <select wire:model.live="filterCategoryId" class="ml-2 h-11 rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Item List --}}
        <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Active</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono">{{ $item['sku'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item['name'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item['category_name'] }}</td>
                            <td class="px-4 py-3 text-sm">${{ number_format((float)$item['price'], 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $item['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $item['is_active'] ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="editItem({{ $item['id'] }})" class="min-h-11 rounded px-2 py-1 text-blue-600 hover:bg-blue-50 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">Edit</button>
                                <button wire:click="deleteItem({{ $item['id'] }})" wire:confirm="Delete this menu item?" class="ml-2 min-h-11 rounded px-2 py-1 text-red-600 hover:bg-red-50 hover:underline focus:outline-none focus:ring-2 focus:ring-red-200">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No menu items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
