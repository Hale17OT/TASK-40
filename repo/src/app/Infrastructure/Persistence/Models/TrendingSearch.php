<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class TrendingSearch extends Model
{
    protected $fillable = [
        'term',
        'sort_order',
        'pinned_by',
        'location_id',
    ];
}
