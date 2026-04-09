<div class="flex min-h-screen items-center justify-center bg-gray-100">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-gray-900">HarborBite</h1>
            <p class="mt-2 text-sm text-gray-500">Password Change Required</p>
        </div>

        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm text-amber-800">You are using a default password. Please change it before continuing.</p>
        </div>

        <form wire:submit="changePassword">
            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                    <input
                        wire:model="current_password"
                        type="password"
                        id="current_password"
                        autocomplete="current-password"
                        class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="Enter current password"
                    >
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input
                        wire:model="new_password"
                        type="password"
                        id="new_password"
                        autocomplete="new-password"
                        class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="Minimum 8 characters"
                    >
                    @error('new_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input
                        wire:model="new_password_confirmation"
                        type="password"
                        id="new_password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="Repeat new password"
                    >
                </div>

                @if (auth()->user()?->manager_pin)
                    <div>
                        <label for="new_manager_pin" class="block text-sm font-medium text-gray-700">New Manager PIN (4 digits)</label>
                        <input
                            wire:model="new_manager_pin"
                            type="password"
                            id="new_manager_pin"
                            inputmode="numeric"
                            maxlength="4"
                            class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="Enter new 4-digit PIN"
                        >
                        @error('new_manager_pin')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="mt-6 flex h-12 w-full items-center justify-center rounded-lg bg-blue-700 text-base font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove>Update Password</span>
                <span wire:loading>
                    <svg class="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
            </button>
        </form>
    </div>
</div>
