<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'HarborBite' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="flex h-full flex-col">
        {{-- Header --}}
        <header class="flex items-center justify-between bg-blue-700 px-6 py-3 text-white shadow-md">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold tracking-tight">HarborBite</h1>
            </div>
            <div class="flex items-center gap-4">
                <div id="cart-indicator" class="relative">
                    <a href="/cart" class="flex min-h-11 items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium transition-colors hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-700 active:bg-blue-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        <span>Cart</span>
                        <span x-data="{ count: 0 }" x-on:cart-updated.window="count = $event.detail.count" x-show="count > 0" x-text="count" class="ml-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold"></span>
                    </a>
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    <script>
        // Device fingerprint collection
        document.addEventListener('DOMContentLoaded', function() {
            const traits = {
                width: screen.width,
                height: screen.height,
                colorDepth: screen.colorDepth,
            };
            // Store for sending with requests
            window.__deviceTraits = traits;
        });
    </script>
</body>
</html>
