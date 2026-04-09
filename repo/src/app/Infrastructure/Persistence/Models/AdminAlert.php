<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAlert extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'message',
        'threshold_value',
        'actual_value',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'acknowledged_at' => 'datetime',
        ];
    }
}
