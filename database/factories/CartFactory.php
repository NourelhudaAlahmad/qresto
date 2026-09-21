<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory(),

            'restaurant_id' => function (array $attributes) {
                $sessionId = (int) $attributes['table_session_id'];

                return TableSession::query()
                    ->findOrFail($sessionId)
                    ->restaurant_id;
            },

            'currency' => function (array $attributes) {
                $sessionId = (int) $attributes['table_session_id'];

                return TableSession::query()
                    ->with('restaurant')
                    ->findOrFail($sessionId)
                    ->restaurant
                    ->currency;
            },
        ];
    }

    public function forTableSession(TableSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $session->restaurant_id,
            'table_session_id' => $session->id,
            'currency' => $session->restaurant->currency,
        ]);
    }
}
