<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'translations',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->orderBy('sort_order');
    }
}
