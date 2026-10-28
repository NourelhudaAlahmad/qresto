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
        // Without the tenant scope (the guest has no restaurant yet), but a
        // retired table's sticker must not open a session.
        $table = RestaurantTable::withoutGlobalScopes()
            ->where('qr_token', $qrToken)
            ->whereNull('retired_at')
            ->firstOrFail();

        $restaurant = $table->restaurant;

        if ($restaurant === null) {
            throw new RuntimeException('Restaurant not found.');
        }

        $this->currentRestaurant->set($restaurant);

        return TableSession::openFor($table);
    }
}
