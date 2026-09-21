<?php

namespace Database\Seeders;

use App\Enums\TableState;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AlBustanTablesSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $tables = [
            [
                'number' => '01',
                'seats' => 2,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 1,
            ],
            [
                'number' => '02',
                'seats' => 4,
                'state' => TableState::SEATED,
                'party_size' => 2,
                'seated_at' => now()->subMinutes(41),
                'sort_order' => 2,
            ],
            [
                'number' => '03',
                'seats' => 2,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 3,
            ],
            [
                'number' => '04',
                'seats' => 4,
                'state' => TableState::ORDERED,
                'party_size' => 2,
                'seated_at' => now()->subMinutes(2),
                'sort_order' => 4,
            ],
            [
                'number' => '05',
                'seats' => 6,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 5,
            ],
            [
                'number' => '06',
                'seats' => 2,
                'state' => TableState::SEATED,
                'party_size' => 2,
                'seated_at' => now()->subMinutes(8),
                'sort_order' => 6,
            ],
            [
                'number' => '07',
                'seats' => 6,
                'state' => TableState::BILL,
                'party_size' => 4,
                'seated_at' => now()->subMinutes(26),
                'sort_order' => 7,
            ],
            [
                'number' => '08',
                'seats' => 2,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 8,
            ],
            [
                'number' => '09',
                'seats' => 4,
                'state' => TableState::SEATED,
                'party_size' => 3,
                'seated_at' => now()->subMinutes(12),
                'sort_order' => 9,
            ],
            [
                'number' => '10',
                'seats' => 4,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 10,
            ],
            [
                'number' => '11',
                'seats' => 2,
                'state' => TableState::FREE,
                'party_size' => 0,
                'seated_at' => null,
                'sort_order' => 11,
            ],
            [
                'number' => '12',
                'seats' => 4,
                'state' => TableState::ORDERED,
                'party_size' => 2,
                'seated_at' => now()->subMinutes(14),
                'sort_order' => 12,
            ],
        ];

        foreach ($tables as $table) {
            $restaurant->tables()->updateOrCreate(
                [
                    'number' => $table['number'],
                ],
                [
                    'seats' => $table['seats'],
                    'state' => $table['state'],
                    'party_size' => $table['party_size'],
                    'seated_at' => $table['seated_at'],
                    'sort_order' => $table['sort_order'],
                    'qr_token' => Str::random(32),
                ],
            );
        }
    }
}
