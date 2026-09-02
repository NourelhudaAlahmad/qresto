<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Database\Factories\AllergenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<AllergenFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'translations',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<MenuItem, $this>
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->withPivot('may_contain');
    }
}
