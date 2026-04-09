<div>
    <h2 class="mb-6 text-2xl font-bold text-gray-900">Security Audit Log</h2>

    {{-- Type Filter --}}
    <div class="mb-4 flex gap-2">
        <button wire:click="$set('typeFilter', '')" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 {{ !$typeFilter ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 active:bg-gray-400' }}">
            All
        </button>
        @foreach (['rate_limit' => 'Rate Limit', 'blacklist_block' => 'Blacklist', 'login_failure' => 'Failed Login', 'login_success' => 'Login', 'captcha_triggered' => 'CAPTCHA'] as $type => $label)
            <button wire:click="$set('typeFilter', '{{ $type }}')" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 {{ $typeFilter === $type ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 active:bg-gray-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Rule Hit Logs --}}
    <div class="rounded-xl bg-white shadow-sm">
        <div class="border-b px-6 py-3">
            <h3 class="font-bold text-gray-900">Rule Hit Logs</h3>
        </div>
        <div class="divide-y">
            @forelse ($logs as $log)
                <div class="flex items-center justify-between px-6 py-3">
                    <div>
                        @php
                            $typeBadge = match($log['type']) {
                                'rate_limit' => 'bg-yellow-100 text-yellow-800',
                                'blacklist_block' => 'bg-red-100 text-red-800',
                                'login_failure' => 'bg-orange-100 text-orange-800',
                                'login_success' => 'bg-green-100 text-green-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                        @endphp
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $typeBadge }}">{{ $log['type'] }}</span>
                        <span class="ml-2 text-sm text-gray-600">IP: {{ $log['ip_address'] ?? 'N/A' }}</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $log['created_at'] }}</span>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-gray-500">No log entries found.</div>
            @endforelse
        </div>
    </div>

    {{-- Privilege Escalation Logs --}}
    <div class="mt-6 rounded-xl bg-white shadow-sm">
        <div class="border-b px-6 py-3">
            <h3 class="font-bold text-gray-900">Privilege Escalation Log</h3>
        </div>
        <div class="divide-y">
            @forelse ($escalations as $esc)
                <div class="px-6 py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">{{ $esc['action'] }}</span>
                            <span class="ml-2 text-sm font-medium text-gray-900">{{ $esc['manager_name'] }}</span>
                        </div>
                        <span class="text-xs text-gray-400">{{ $esc['created_at'] }}</span>
                    </div>
                    @if ($esc['reason'])
                        <p class="mt-1 text-sm text-gray-500">Reason: {{ $esc['reason'] }}</p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-8 text-center text-gray-500">No escalation entries.</div>
            @endforelse
        </div>
    </div>
</div>
