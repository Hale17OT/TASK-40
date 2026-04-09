<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SecurityRulesManager extends Component
{
    // Blacklist form
    public string $blType = 'device';
    public string $blValue = '';
    public string $blReason = '';
    public ?string $blExpires = null;

    // Whitelist form
    public string $wlType = 'device';
    public string $wlValue = '';
    public string $wlReason = '';

    // Banned words
    public string $newBannedWord = '';

    public array $blacklists = [];
    public array $whitelists = [];
    public array $bannedWords = [];
    public ?string $message = null;
    public ?string $error = null;

    public function mount(): void
    {
        $this->loadLists();
        $this->loadBannedWords();
    }

    public function addBlacklist(): void
    {
        $this->validate([
            'blType' => 'required|in:device,ip,username',
            'blValue' => 'required|string|max:255',
        ]);

        DB::table('security_blacklists')->insert([
            'type' => $this->blType,
            'value' => $this->blValue,
            'reason' => $this->blReason ?: null,
            'created_by' => auth()->id(),
            'expires_at' => $this->blExpires ? \Carbon\Carbon::parse($this->blExpires) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->blValue = '';
        $this->blReason = '';
        $this->blExpires = null;
        $this->message = 'Blacklist entry added.';
        $this->loadLists();
    }

    public function removeBlacklist(int $id): void
    {
        DB::table('security_blacklists')->where('id', $id)->delete();
        $this->message = 'Blacklist entry removed.';
        $this->loadLists();
    }

    public function addWhitelist(): void
    {
        $this->validate([
            'wlType' => 'required|in:device,ip,username',
            'wlValue' => 'required|string|max:255',
        ]);

        DB::table('security_whitelists')->insert([
            'type' => $this->wlType,
            'value' => $this->wlValue,
            'reason' => $this->wlReason ?: null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->wlValue = '';
        $this->wlReason = '';
        $this->message = 'Whitelist entry added.';
        $this->loadLists();
    }

    public function removeWhitelist(int $id): void
    {
        DB::table('security_whitelists')->where('id', $id)->delete();
        $this->message = 'Whitelist entry removed.';
        $this->loadLists();
    }

    public function addBannedWord(): void
    {
        $word = strtolower(trim($this->newBannedWord));
        if ($word === '') {
            $this->error = 'Word cannot be empty.';
            return;
        }

        $exists = DB::table('banned_words')->where('word', $word)->exists();
        if ($exists) {
            $this->error = 'Word already banned.';
            return;
        }

        DB::table('banned_words')->insert([
            'word' => $word,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Invalidate cached banned words so ProfanityFilter picks up changes
        \Illuminate\Support\Facades\Cache::forget('banned_words');

        $this->newBannedWord = '';
        $this->message = "Banned word \"{$word}\" added.";
        $this->loadBannedWords();
    }

    public function removeBannedWord(int $id): void
    {
        DB::table('banned_words')->where('id', $id)->delete();
        \Illuminate\Support\Facades\Cache::forget('banned_words');
        $this->message = 'Banned word removed.';
        $this->loadBannedWords();
    }

    private function loadBannedWords(): void
    {
        $this->bannedWords = DB::table('banned_words')
            ->orderBy('word')
            ->limit(100)
            ->get()
            ->map(fn ($w) => (array) $w)
            ->toArray();
    }

    private function loadLists(): void
    {
        $this->blacklists = DB::table('security_blacklists')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($b) => (array) $b)
            ->toArray();

        $this->whitelists = DB::table('security_whitelists')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($w) => (array) $w)
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.security-rules-manager');
    }
}
