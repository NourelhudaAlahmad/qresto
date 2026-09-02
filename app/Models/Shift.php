<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shift extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<RestaurantTable, $this>
     */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantTable::class,
            'table_user',
            'shift_id',
            'table_id',
        );
    }
}
