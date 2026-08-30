<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use App\Enums\TableState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

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
    ];

    protected function casts(): array
    {
        return [
            'state' => TableState::class,
            'party_size' => 'integer',
            'seated_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

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

