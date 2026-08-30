<?php

namespace App\Services;

use App\CurrentRestaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use RuntimeException;

class TableSessionService
{
    public function __construct(
        private readonly CurrentRestaurant $currentRestaurant,
    ) {}

    public function startFromQrToken(string $qrToken): TableSession
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->where('qr_token', $qrToken)
            ->first();

        if ($table === null) {
            throw new RuntimeException('Table not found.');
        }

        $restaurant = $table->restaurant;

        if ($restaurant === null) {
            throw new RuntimeException('Restaurant not found.');
        }

        $this->currentRestaurant->set($restaurant);

        return TableSession::openFor($table);
    }
}