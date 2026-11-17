<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Database\Factories\RestaurantRoleCapabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One restaurant's grant or revoke of one capability for one role, on top
 * of the default grid. Read through App\Services\RestaurantCapabilities.
 */
class RestaurantRoleCapability extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<RestaurantRoleCapabilityFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'role',
        'capability',
        'granted',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
        ];
    }
}
