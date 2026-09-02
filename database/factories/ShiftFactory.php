<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->startOfDay()->addHours(8);

        return [
            'restaurant_id' => null,
            'user_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(8),
        ];
    }
}
