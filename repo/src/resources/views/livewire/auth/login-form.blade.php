<div class="flex min-h-screen items-center justify-center bg-gray-100">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-gray-900">HarborBite</h1>
            <p class="mt-2 text-sm text-gray-500">Staff Login</p>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <form wire:submit="login">
            <div class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input
                        wire:model="username"
                        type="text"
                        id="username"
                        autocomplete="username"
                        class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-gray-100 disabled:opacity-50"
                        placeholder="Enter your username"
                    >
                    @error('username')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input
                        wire:model="password"
                        type="password"
                        id="password"
                        autocomplete="current-password"
                        class="mt-1 block h-12 w-full rounded-lg border border-gray-300 px-4 text-base transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-gray-100 disabled:opacity-50"
                        placeholder="Enter your password"
                    >
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if ($showCaptcha)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Security Check</label>
                        <div class="mt-1 flex items-center gap-4">
                            <div class="rounded-lg border bg-gray-50 p-3 text-lg font-bold">
                                {{ $captchaQuestion }}
                            </div>
                            <input
                                wire:model="captchaAnswer"
                                type="text"
                                class="block h-12 w-24 rounded-lg border border-gray-300 px-4 text-base text-center focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="?"
                            >
                        </div>
                        @error('captchaAnswer')
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
                <span wire:loading.remove>Sign In</span>
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
