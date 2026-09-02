<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'menu_category_id',
        'name',
        'description',
        'translations',
        'price',
        'photo_path',
        'prep_minutes',
        'is_available',
        'is_scheduled',
        'available_from',
        'available_until',
        'sort_order',
        'dietary_tags',
        'chef_flag',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'price' => MoneyCast::class,
            'prep_minutes' => 'integer',
            'is_available' => 'boolean',
            'is_scheduled' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'sort_order' => 'integer',
            'dietary_tags' => 'array',
            'chef_flag' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)
            ->orderBy('sort_order');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(MenuItemAddon::class)
            ->orderBy('sort_order');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class)
            ->withPivot('may_contain');
    }

    public function scopeAvailableNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_available', true)
            ->where(function (Builder $query) use ($now) {
                $query
                    ->where('is_scheduled', false)
                    ->orWhere(function (Builder $query) use ($now) {
                        $query
                            ->where('is_scheduled', true)
                            ->where(function (Builder $query) use ($now) {
                                $query
                                    ->whereNull('available_from')
                                    ->orWhere('available_from', '<=', $now);
                            })
                            ->where(function (Builder $query) use ($now) {
                                $query
                                    ->whereNull('available_until')
                                    ->orWhere('available_until', '>=', $now);
                            });
                    });
            });
    }
}
