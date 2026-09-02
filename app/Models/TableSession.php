<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TableSession extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

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

    public function table(): BelongsTo
    {
        return $this->belongsTo(
            RestaurantTable::class,
            'restaurant_table_id',
        );
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
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
