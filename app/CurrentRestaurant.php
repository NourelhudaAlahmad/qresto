<?php

namespace App;

use App\Models\Restaurant;

class CurrentRestaurant
{
    private ?Restaurant $restaurant = null;

    public function set(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function get(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function id(): ?int
    {
        return $this->restaurant?->id;
    }

    public function clear(): void
    {
        $this->restaurant = null;
    }
}
