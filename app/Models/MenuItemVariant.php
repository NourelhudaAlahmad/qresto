<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\MenuItemVariantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariant extends Model
{
    /** @use HasFactory<MenuItemVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'label',
        'price_delta',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => MoneyCast::class,
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Resolve the currency from the parent menu item.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::get(function (): string {
            $menuItem = $this->relationLoaded('menuItem')
                ? $this->menuItem
                : $this->menuItem()->with('restaurant')->first();

            if ($menuItem === null) {
                return 'TRY';
            }

            return $menuItem->restaurant->currency ?? 'TRY';
        });
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
