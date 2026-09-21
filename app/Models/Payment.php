<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'tip_amount',
        'gateway',
        'gateway_intent_id',
        'gateway_status',
        'requires_3ds',
        'failure_reason',
        'taken_by',
        'paid_at',
        'refunded_amount',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'tip_amount' => MoneyCast::class,
            'requires_3ds' => 'boolean',
            'paid_at' => 'datetime',
            'refunded_amount' => MoneyCast::class,
            'refunded_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'taken_by',
        );
    }
}
