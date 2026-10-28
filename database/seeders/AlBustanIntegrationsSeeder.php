<?php

namespace Database\Seeders;

use App\Models\Integration;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

/**
 * The three rows the design's health card shows. They start "unknown":
 * nothing has reported yet, and a row must never claim to be healthy
 * because nobody checked. Issue a device token with
 * `php artisan qresto:integration-token {id}`.
 */
class AlBustanIntegrationsSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();

        foreach ([
            ['kind' => 'gateway', 'name' => 'Payment gateway'],
            ['kind' => 'kds', 'name' => 'Kitchen display'],
            ['kind' => 'printer', 'name' => 'Receipt printer, pass'],
        ] as $row) {
            Integration::query()->withoutGlobalScopes()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'kind' => $row['kind'], 'name' => $row['name']],
            );
        }
    }
}
