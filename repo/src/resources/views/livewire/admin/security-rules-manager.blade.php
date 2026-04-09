<div class="space-y-8">
    <h2 class="text-2xl font-bold text-gray-900">Security Rules</h2>

    @if ($message)
        <div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ $message }}</div>
    @endif

    {{-- Blacklist --}}
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Blacklist</h3>
        <form wire:submit="addBlacklist" class="mt-4 flex flex-wrap gap-3">
            <select wire:model="blType" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                <option value="device">Device</option>
                <option value="ip">IP/CIDR</option>
                <option value="username">Username</option>
            </select>
            <input wire:model="blValue" type="text" placeholder="Value" class="h-11 flex-1 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" required>
            <input wire:model="blReason" type="text" placeholder="Reason" class="h-11 w-48 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <input wire:model="blExpires" type="datetime-local" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <button type="submit" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 active:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Add</button>
        </form>

        <div class="mt-4 divide-y">
            @foreach ($blacklists as $bl)
                <div class="flex items-center justify-between py-2">
                    <div>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-mono">{{ $bl['type'] }}</span>
                        <span class="ml-2 text-sm font-medium">{{ $bl['value'] }}</span>
                        @if ($bl['reason'])
                            <span class="ml-2 text-xs text-gray-400">{{ $bl['reason'] }}</span>
                        @endif
                        @if ($bl['expires_at'])
                            <span class="ml-2 text-xs text-orange-600">Expires: {{ $bl['expires_at'] }}</span>
                        @endif
                    </div>
                    <button wire:click="removeBlacklist({{ $bl['id'] }})" class="min-h-11 rounded-lg px-3 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-200 active:bg-red-100">Remove</button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Whitelist --}}
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Whitelist</h3>
        <form wire:submit="addWhitelist" class="mt-4 flex flex-wrap gap-3">
            <select wire:model="wlType" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                <option value="device">Device</option>
                <option value="ip">IP/CIDR</option>
                <option value="username">Username</option>
            </select>
            <input wire:model="wlValue" type="text" placeholder="Value" class="h-11 flex-1 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" required>
            <input wire:model="wlReason" type="text" placeholder="Reason" class="h-11 w-48 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <button type="submit" class="h-11 rounded-lg bg-green-600 px-4 text-sm font-medium text-white transition-colors hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 active:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">Add</button>
        </form>

        <div class="mt-4 divide-y">
            @foreach ($whitelists as $wl)
                <div class="flex items-center justify-between py-2">
                    <div>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-mono">{{ $wl['type'] }}</span>
                        <span class="ml-2 text-sm font-medium">{{ $wl['value'] }}</span>
                        @if ($wl['reason'])
                            <span class="ml-2 text-xs text-gray-400">{{ $wl['reason'] }}</span>
                        @endif
                    </div>
                    <button wire:click="removeWhitelist({{ $wl['id'] }})" class="min-h-11 rounded-lg px-3 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-200 active:bg-red-100">Remove</button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Banned Words --}}
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Banned Search Terms</h3>
        <p class="mt-1 text-sm text-gray-500">Words that trigger search blocking with profanity filter.</p>

        <form wire:submit="addBannedWord" class="mt-4 flex gap-3">
            <input wire:model="newBannedWord" type="text" placeholder="Word to ban" class="h-11 flex-1 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" required>
            <button type="submit" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 active:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Ban Word</button>
        </form>

        @if ($error)
            <div class="mt-2 rounded-lg bg-red-50 p-2 text-sm text-red-700">{{ $error }}</div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($bannedWords as $bw)
                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-sm text-red-700">
                    {{ $bw['word'] }}
                    <button wire:click="removeBannedWord({{ $bw['id'] }})" class="ml-1 text-red-400 transition-colors hover:text-red-700">&times;</button>
                </span>
            @endforeach
            @if (empty($bannedWords))
                <p class="text-sm text-gray-400">No banned words configured.</p>
            @endif
        </div>
    </div>
</div>
