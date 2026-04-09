<div>
    @if($message)
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-800">{{ $message }}</div>
    @endif
    @if($error)
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    {{-- User Form --}}
    <div class="mb-6 rounded-lg border bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-lg font-semibold">{{ $editingUserId ? 'Edit User' : 'Add User' }}</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model="name" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" wire:model="username" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password {{ $editingUserId ? '(leave blank to keep current)' : '' }}</label>
                <input type="password" wire:model="password" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Role</label>
                <select wire:model="role" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($availableRoles as $r)
                        <option value="{{ $r }}">{{ ucwords(str_replace('_', ' ', $r)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Manager PIN (optional)</label>
                <input type="text" wire:model="managerPin" class="mt-1 h-11 w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" maxlength="6" />
            </div>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="isActive" class="rounded" /> Active
                </label>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button wire:click="saveUser" class="min-h-11 rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $editingUserId ? 'Update' : 'Create' }}</button>
            @if($editingUserId)
                <button wire:click="resetForm" class="min-h-11 rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 active:bg-gray-400">Cancel</button>
            @endif
        </div>
    </div>

    {{-- User List --}}
    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Username</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($users as $user)
                    <tr class="{{ !$user['is_active'] ? 'bg-gray-50 opacity-60' : '' }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $user['name'] }}</td>
                        <td class="px-4 py-3 text-sm font-mono">{{ $user['username'] }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                @if($user['role'] === 'administrator') bg-purple-100 text-purple-800
                                @elseif($user['role'] === 'manager') bg-blue-100 text-blue-800
                                @else bg-gray-100 text-gray-700
                                @endif
                            ">{{ ucwords(str_replace('_', ' ', $user['role'])) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $user['is_active'] ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <button wire:click="editUser({{ $user['id'] }})" class="min-h-11 rounded px-2 py-1 text-blue-600 hover:bg-blue-50 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">Edit</button>
                            <button wire:click="toggleActive({{ $user['id'] }})" class="ml-2 min-h-11 rounded px-2 py-1 {{ $user['is_active'] ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50' }} hover:underline focus:outline-none focus:ring-2 focus:ring-blue-200">
                                {{ $user['is_active'] ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
