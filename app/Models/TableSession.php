<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Carbon\CarbonInterface;
use Database\Factories\TableSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

class TableSession extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<TableSessionFactory> */
    use HasFactory;

    use MassPrunable;

    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'token',
        'guest_name',
        'party_size',
        'opened_at',
        'closed_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(
            RestaurantTable::class,
            'restaurant_table_id',
        );
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasOne<Cart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Sessions with no guest activity since the cutoff, open or closed:
     * the idle limit is minutes, so these are long over.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeQuietSince(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query->where(function (Builder $query) use ($cutoff): void {
            $query->where('last_seen_at', '<', $cutoff)
                ->orWhere(function (Builder $query) use ($cutoff): void {
                    $query->whereNull('last_seen_at')
                        ->where('opened_at', '<', $cutoff);
                });
        });
    }

    /**
     * A scan that never became an order, quiet past the retention window
     * (#57). Its cart, cart lines and add-ons go with it (cascade); its
     * service requests stay, unlinked. A session with any order is kept:
     * it holds the party size the covers are counted from.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->withoutGlobalScopes()
            ->quietSince(now()->subDays((int) config('qresto.retention.table_sessions_days', 30)))
            ->whereNotExists(function (QueryBuilder $orders): void {
                $orders->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.table_session_id', 'table_sessions.id');
            });
    }

    public static function openFor(RestaurantTable $table): self
    {
        $existingSession = static::withoutGlobalScopes()
            ->where('restaurant_table_id', $table->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if ($existingSession !== null && $existingSession->isActive()) {
            $existingSession->touchActivity();

            return $existingSession;
        }

        $restaurant = $table->restaurant;

        if ($restaurant === null) {
            throw new \RuntimeException('Restaurant not found.');
        }

        return static::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'token' => static::issueToken($table),
            'party_size' => 1,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    protected static function issueToken(RestaurantTable $table): string
    {
        $payload = $table->id.'|'.Str::random(48);

        return hash_hmac(
            'sha256',
            $payload,
            (string) config('app.key'),
        ).'.'.Str::random(16);
    }

    public function isActive(): bool
    {
        return $this->closed_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        $lastSeenAt = ($this->last_seen_at ?? $this->opened_at)->copy();

        return $lastSeenAt
            ->addMinutes(
                (int) config(
                    'qresto.table_session_idle_minutes',
                    30,
                ),
            )
            ->isPast();
    }

    public function touchActivity(): void
    {
        $this->update([
            'last_seen_at' => now(),
        ]);
    }

    public function end(): void
    {
        $this->update([
            'closed_at' => now(),
        ]);
    }
}
