<?php

namespace App\Models;

use Database\Factories\OrderLineFactory;
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
            'unit_price' => 'decimal:2',
            'qty' => 'integer',
            'line_total' => 'decimal:2',
        ];
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
