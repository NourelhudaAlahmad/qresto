<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\OrderLineOptionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineOption extends Model
{
    /** @use HasFactory<OrderLineOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'order_line_id',
        'kind',
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
     * Resolve the currency from the parent order line.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->orderLine->currency,
        );
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
