<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\CartLineFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartLine extends Model
{
    /** @use HasFactory<CartLineFactory> */
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'menu_item_id',
        'name_snapshot',
        'unit_price',
        'qty',
        'line_total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'qty' => 'integer',
            'line_total' => MoneyCast::class,
        ];
    }

    /**
     * Resolve the currency from the parent cart.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('cart')) {
                return $this->cart->currency;
            }

            return Cart::query()
                ->whereKey($this->getAttribute('cart_id'))
                ->value('currency') ?? 'TRY';
        });
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
