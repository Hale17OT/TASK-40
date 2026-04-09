<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SecurityAuditLog extends Component
{
    public string $typeFilter = '';
    public array $logs = [];
    public array $escalations = [];
    public int $page = 1;

    public function mount(): void
    {
        $this->loadLogs();
    }

    public function updatedTypeFilter(): void
    {
        $this->page = 1;
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $query = DB::table('rule_hit_logs')
            ->orderByDesc('created_at');

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        $this->logs = $query
            ->offset(($this->page - 1) * 50)
            ->limit(50)
            ->get()
            ->map(function ($log) {
                $arr = (array) $log;
                $arr['details'] = is_string($arr['details']) ? json_decode($arr['details'], true) : $arr['details'];
                return $arr;
            })
            ->toArray();

        $this->escalations = DB::table('privilege_escalation_logs')
            ->join('users', 'privilege_escalation_logs.manager_id', '=', 'users.id')
            ->orderByDesc('privilege_escalation_logs.created_at')
            ->limit(20)
            ->select([
                'privilege_escalation_logs.*',
                'users.name as manager_name',
            ])
            ->get()
            ->map(function ($e) {
                $arr = (array) $e;
                $arr['metadata'] = is_string($arr['metadata']) ? json_decode($arr['metadata'], true) : $arr['metadata'];
                return $arr;
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.security-audit-log');
    }
}
