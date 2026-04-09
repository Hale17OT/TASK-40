<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityWhitelist extends Model
{
    protected $fillable = [
        'type',
        'value',
        'reason',
        'created_by',
    ];
}
