<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentIntent extends Model
{
    protected $fillable = [
        'order_id', 'reference', 'amount', 'hmac_signature', 'signed_at',
        'nonce', 'nonce_used_at', 'expires_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'signed_at' => 'integer',
            'expires_at' => 'datetime',
            'nonce_used_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmation(): HasOne
    {
        return $this->hasOne(PaymentConfirmation::class);
    }
}
