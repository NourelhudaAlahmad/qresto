<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use App\Enums\TableState;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $table = 'tables';

    protected $fillable = [
        'restaurant_id',
        'number',
        'seats',
        'state',
        'party_size',
        'seated_at',
        'qr_token',
        'sort_order',
        'retired_at',
    ];

    public const ACTIVE_SCOPE = 'active';

    /**
     * Retired tables (#29) vanish from the floor, the QR sheet, the table
     * picker and the staff drawer. Their orders keep pointing at them.
     * Settings that must see them use withoutGlobalScope(ACTIVE_SCOPE).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            self::ACTIVE_SCOPE,
            fn ($query) => $query->whereNull($query->qualifyColumn('retired_at')),
        );
    }

    /**
     * A fresh, unguessable token for the QR sticker.
     */
    public static function newQrToken(): string
    {
        return Str::random(40);
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    protected function casts(): array
    {
        return [
            'state' => TableState::class,
            'party_size' => 'integer',
            'seated_at' => 'datetime',
            'sort_order' => 'integer',
            'retired_at' => 'datetime',
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
     * @return HasMany<TableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'restaurant_table_id');
    }

    public function occupy(int $partySize): void
    {
        $this->update([
            'state' => TableState::SEATED,
            'party_size' => $partySize,
            'seated_at' => now(),
        ]);
    }

    public function markOrdered(): void
    {
        $this->update([
            'state' => TableState::ORDERED,
        ]);
    }

    public function requestBill(): void
    {
        $this->update([
            'state' => TableState::BILL,
        ]);
    }

    public function free(): void
    {
        $this->update([
            'state' => TableState::FREE,
            'party_size' => 0,
            'seated_at' => null,
        ]);
    }
}
