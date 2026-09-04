<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        $fromStatus = fake()->randomElement(OrderStatus::cases());
        $toStatus = fake()->randomElement(OrderStatus::cases());

        return [
            'order_id' => Order::factory(),
            'from_status' => $fromStatus->value,
            'to_status' => $toStatus->value,
            'actor_id' => null,
            'actor_kind' => fake()->randomElement([
                'guest',
                'staff',
                'system',
            ]),
            'occurred_at' => now(),
            'meta' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function byGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => null,
            'actor_kind' => 'guest',
        ]);
    }

    public function byStaff(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => $user->id,
            'actor_kind' => 'staff',
        ]);
    }

    public function bySystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => null,
            'actor_kind' => 'system',
        ]);
    }

    public function transition(OrderStatus $from, OrderStatus $to): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $from->value,
            'to_status' => $to->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        return $this->state([
            'meta' => $meta,
        ]);
    }
}
