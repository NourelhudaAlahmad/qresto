<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\MenuItemAddonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemAddon extends Model
{
    /** @use HasFactory<MenuItemAddonFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'label',
        'price_delta',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => MoneyCast::class,
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
