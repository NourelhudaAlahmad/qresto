<?php

namespace App\Models;

use Database\Factories\OrderLineOptionFactory;
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
            'price_delta' => 'decimal:2',
        ];
    }

    /**
     * السطر الذي ينتمي إليه هذا الخيار.
     *
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
