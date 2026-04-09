<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'sku',
        'menu_category_id',
        'name',
        'description',
        'price',
        'tax_category',
        'is_active',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function hasAttribute(string $key): bool
    {
        $attrs = $this->attributes['attributes'] ?? [];
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true) ?? [];
        }
        return isset($attrs[$key]) && $attrs[$key] === true;
    }
}
