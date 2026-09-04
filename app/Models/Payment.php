<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
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
            'amount' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'requires_3ds' => 'boolean',
            'paid_at' => 'datetime',
            'refunded_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * الطلب المرتبط بالدفع.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * الموظف الذي أخذ الدفعة.
     *
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
