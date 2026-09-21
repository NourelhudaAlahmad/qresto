<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'name_snapshot',
        'unit_price',
        'qty',
        'line_total',
        'note',
        'station',
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
     * Resolve the currency from the parent order.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('order')) {
                return $this->order->currency;
            }

            return Order::query()
                ->whereKey($this->getAttribute('order_id'))
                ->value('currency') ?? 'TRY';
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(
            MenuItem::class,
            'menu_item_id',
        );
    }

    /**
     * @return HasMany<OrderLineOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(OrderLineOption::class);
    }
}
