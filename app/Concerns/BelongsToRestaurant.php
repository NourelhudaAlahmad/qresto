<?php

namespace App\Concerns;

use App\CurrentRestaurant;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::creating(function (Model $model) {
            if ($model->restaurant_id === null) {
                $model->restaurant_id = app(CurrentRestaurant::class)->id();
            }
        });

        static::addGlobalScope('restaurant', function (Builder $builder) {
            $restaurantId = app(CurrentRestaurant::class)->id();

            if ($restaurantId !== null) {
                $builder->where(
                    $builder->getModel()->getTable() . '.restaurant_id',
                    $restaurantId,
                );
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
