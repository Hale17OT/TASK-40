<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfirmation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_intent_id', 'confirmed_by', 'method', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
