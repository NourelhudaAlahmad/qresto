<?php

namespace App\Models;

use Database\Factories\OrderEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    /** @use HasFactory<OrderEventFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_kind',
        'occurred_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * الطلب المرتبط بهذا الحدث.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * الموظف أو المستخدم الذي نفّذ الحدث.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_id',
        );
    }
}
