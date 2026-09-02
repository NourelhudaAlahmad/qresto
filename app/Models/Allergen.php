<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    use BelongsToRestaurant;
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

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->withPivot('may_contain');
    }
}
