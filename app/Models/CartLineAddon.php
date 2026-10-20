<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartLineAddon extends Model
{
    protected $fillable = [
        'cart_line_id',
        'menu_item_addon_id',
        'label_snapshot',
        'price_delta',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => MoneyCast::class,
        ];
    }

    /**
     * Resolve the currency from the parent cart line.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('cartLine')) {
                $cartLine = $this->cartLine;
            } else {
                $cartLine = $this->cartLine()
                    ->with('cart')
                    ->first();
            }

            if ($cartLine === null) {
                return 'TRY';
            }

            if (! $cartLine->relationLoaded('cart')) {
                $cartLine->load('cart');
            }

            return $cartLine->cart->currency ?? 'TRY';
        });
    }

    /**
     * @return BelongsTo<CartLine, $this>
     */
    public function cartLine(): BelongsTo
    {
        return $this->belongsTo(CartLine::class);
    }

    /**
     * @return BelongsTo<MenuItemAddon, $this>
     */
    public function menuItemAddon(): BelongsTo
    {
        return $this->belongsTo(MenuItemAddon::class);
    }
}
