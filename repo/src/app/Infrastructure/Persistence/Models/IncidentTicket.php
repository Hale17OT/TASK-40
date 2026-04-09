<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentTicket extends Model
{
    protected $fillable = [
        'order_id', 'payment_intent_id', 'type', 'status',
        'assigned_to', 'resolution_reason_code', 'receipt_reference',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_reference' => 'encrypted',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
