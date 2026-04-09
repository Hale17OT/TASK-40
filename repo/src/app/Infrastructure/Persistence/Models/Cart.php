<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'session_id',
        'device_fingerprint_id',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
