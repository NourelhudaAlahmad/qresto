<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\BelongsToRestaurant;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
    use BelongsToRestaurant;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'table_session_id',
        'code',
        'service_date',
        'guest_name',
        'assigned_user_id',
        'status',
        'placed_at',
        'subtotal',
        'service_pct',
        'service_amount',
        'tip_amount',
        'discount_amount',
        'promo_code',
        'split_ways',
        'share_amount',
        'idempotency_key',
        'total',
        'is_paid',
        'paid_at',
        'table_note',
        'void_reason',
        'voided_by',
        'voided_at',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,

            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',

            'subtotal' => MoneyCast::class,
            'service_pct' => 'decimal:2',
            'service_amount' => MoneyCast::class,
            'tip_amount' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'share_amount' => MoneyCast::class,
            'total' => MoneyCast::class,

            'split_ways' => 'integer',
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
        // chaperone(): each line knows its order, so reading its money
        // (whose currency is the order's) costs no query per line.
        return $this->hasMany(OrderLine::class)->chaperone();
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
        // chaperone(): as for lines, a payment's currency is its order's.
        return $this->hasMany(Payment::class)->chaperone();
    }

    /**
     * Active orders are orders that have not reached a terminal state.
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
     * Orders the guest has actually finished placing. A card order waiting
     * on 3-D Secure is not one yet: the guest may still abandon it, and
     * then it is discarded. Staff screens must not show it, or a waiter
     * could send an unpaid order to the kitchen.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeReleasedToStaff(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'payments',
            fn (Builder $payments) => self::unfinishedOnlinePayment($payments),
        );
    }

    /**
     * Whether a card payment of this order is still unfinished: waiting on
     * 3-D Secure, or not yet answered by the gateway.
     */
    public function isAwaitingCardAuthentication(): bool
    {
        return $this->payments()
            ->where(fn (Builder $payments) => self::unfinishedOnlinePayment($payments))
            ->exists();
    }

    /**
     * A card or wallet payment the guest has not finished: waiting on 3-D
     * Secure, or created and not yet answered by the gateway (no intent).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $payments
     * @return Builder<TModel>
     */
    public static function unfinishedOnlinePayment(Builder $payments): Builder
    {
        return $payments->where(fn (Builder $query) => $query
            ->where('status', PaymentStatus::REQUIRES_ACTION->value)
            ->orWhere(fn (Builder $query) => $query
                ->where('status', PaymentStatus::PENDING->value)
                ->whereNull('gateway_intent_id')
                ->whereIn('method', [PaymentMethod::CARD->value, PaymentMethod::WALLET->value])));
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('is_paid', false);
    }

    /**
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

        if ($next === OrderStatus::SERVED) {
            $this->closeIfSettled($actor);
        }

        return $this;
    }

    /**
     * An order is closed (PAID) once it is both served and fully paid,
     * whichever comes last. A card or wallet order is paid before it is
     * cooked, so serving it closes it; a cash bill closes when it is paid.
     */
    public function closeIfSettled(User|int|string|null $actor = null): void
    {
        if ($this->status === OrderStatus::SERVED && $this->is_paid) {
            $this->transitionTo(OrderStatus::PAID, $actor);
        }
    }
}
