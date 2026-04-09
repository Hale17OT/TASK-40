<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class RuleHitLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'device_fingerprint_id',
        'ip_address',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * This model is append-only. Updates and deletes are prevented by a PostgreSQL trigger.
     */
    public static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Audit log records cannot be modified.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Audit log records cannot be deleted.');
        });
    }
}
