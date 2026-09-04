<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'table_session_id',
        'code',
        'guest_name',
        'assigned_user_id',
        'status',
        'placed_at',
        'subtotal',
        'service_pct',
        'service_amount',
        'tip_amount',
        'discount_amount',
        'total',
        'is_paid',
        'paid_at',
        'table_note',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'service_pct' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)
            ->orderBy('occurred_at');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * الطلبات النشطة.
     *
     * الطلب النشط هو كل طلب لم يصل إلى paid أو cancelled.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OrderStatus::PAID->value,
            OrderStatus::CANCELLED->value,
        ]);
    }

    /**
     * الطلبات غير المدفوعة.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('is_paid', false);
    }

    /**
     * الطلبات المعيّنة لموظف معيّن.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeForWaiter(
        Builder $query,
        User|int $user,
    ): Builder {
        $userId = $user instanceof User
            ? $user->id
            : $user;

        return $query->where('assigned_user_id', $userId);
    }

    /**
     * الوقت المنقضي منذ وضع الطلب بالدقائق.
     *
     * @return Attribute<int, never>
     */
    protected function elapsedMinutes(): Attribute
    {
        return Attribute::get(
            function (): int {
                if ($this->placed_at === null) {
                    return 0;
                }

                return (int) $this->placed_at->diffInMinutes(now());
            },
        );
    }

    /**
     * تغيير حالة الطلب بالطريقة الوحيدة المسموح بها.
     *
     * هذه الدالة:
     * 1. تتحقق من أن الانتقال مسموح.
     * 2. تحدّث حالة الطلب.
     * 3. تنشئ OrderEvent واحد فقط.
     */
    public function transitionTo(
        OrderStatus $next,
        User|int|string|null $actor = null,
    ): self {
        $current = $this->status;

        if ($current === $next) {
            throw new RuntimeException(
                "Order is already in the {$next->value} status.",
            );
        }

        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException(
                "Invalid order status transition: {$current->value} → {$next->value}.",
            );
        }

        $actorId = null;
        $actorKind = 'system';

        if ($actor instanceof User) {
            $actorId = $actor->id;
            $actorKind = 'staff';
        } elseif (is_int($actor)) {
            $actorId = $actor;
            $actorKind = 'staff';
        } elseif (is_string($actor)) {
            $actorKind = $actor;
        }

        $this->status = $next;
        $this->save();

        $this->events()->create([
            'from_status' => $current->value,
            'to_status' => $next->value,
            'actor_id' => $actorId,
            'actor_kind' => $actorKind,
            'occurred_at' => now(),
        ]);

        return $this;
    }
}
