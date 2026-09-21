<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'table_id' => RestaurantTable::factory(),
            'table_session_id' => null,
            'kind' => fake()->randomElement([
                'water',
                'bread',
                'bill',
                'waiter',
            ]),
            'status' => 'pending',
            'requested_at' => now(),
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ];
    }

    public function forTable(RestaurantTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'table_id' => $table->id,
        ]);
    }

    public function forSession(TableSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'table_id' => $session->restaurant_table_id,
            'table_session_id' => $session->id,
        ]);
    }

    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'water',
        ]);
    }

    public function bread(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'bread',
        ]);
    }

    public function bill(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'bill',
        ]);
    }

    public function waiter(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'waiter',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);
    }

    public function acknowledged(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'acknowledged',
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
