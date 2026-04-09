<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class PrivilegeEscalationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'order_id',
        'manager_id',
        'manager_pin_hash',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Privilege escalation records cannot be modified.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Privilege escalation records cannot be deleted.');
        });
    }
}
