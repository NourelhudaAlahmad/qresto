<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'table_id' => null,
            'table_session_id' => null,
            'code' => '#'.fake()->randomElement([
                'A',
                'B',
                'C',
            ]).'-'.fake()->unique()->numberBetween(1000, 9999),
            'guest_name' => fake()->optional()->name(),
            'assigned_user_id' => null,
            'status' => OrderStatus::PLACED,
            'placed_at' => now(),
            'subtotal' => 0,
            'service_pct' => 0,
            'service_amount' => 0,
            'tip_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'is_paid' => false,
            'paid_at' => null,
            'table_note' => fake()->optional()->sentence(),
            'void_reason' => null,
            'voided_by' => null,
            'voided_at' => null,
        ];
    }

    public function placed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PLACED,
            'is_paid' => false,
            'paid_at' => null,
            'void_reason' => null,
            'voided_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PENDING,
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PREPARING,
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::READY,
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::SERVED,
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PAID,
            'is_paid' => true,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
            'is_paid' => false,
            'paid_at' => null,
            'void_reason' => fake()->sentence(),
            'voided_at' => now(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function withTable(RestaurantTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
        ]);
    }

    public function withTableSession(TableSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $session->restaurant_id,
            'table_id' => $session->restaurant_table_id,
            'table_session_id' => $session->id,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $user->restaurant_id,
            'assigned_user_id' => $user->id,
        ]);
    }
}
