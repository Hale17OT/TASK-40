<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'HarborBite Staff' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-100 font-sans text-gray-900 antialiased">
    <div class="flex h-full flex-col">
        {{-- Header --}}
        <header class="flex items-center justify-between bg-gray-800 px-6 py-3 text-white shadow-md">
            <div class="flex items-center gap-4">
                <h1 class="text-lg font-bold">HarborBite</h1>
                <span class="rounded-md bg-gray-700 px-3 py-1 text-sm font-medium capitalize">
                    {{ auth()->user()->role ?? 'Staff' }}
                </span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-300">{{ auth()->user()->name ?? '' }}</span>
                <form method="POST" action="/logout" class="inline">
                    @csrf
                    <button type="submit" class="min-h-11 rounded-md bg-gray-700 px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 focus:ring-offset-gray-800 active:bg-gray-500">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        {{-- Main Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
