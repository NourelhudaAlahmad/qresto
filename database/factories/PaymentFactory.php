<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => fake()->randomElement([
                'cash',
                'card',
                'online',
            ]),
            'status' => 'pending',
            'amount' => fake()->randomFloat(2, 5, 200),
            'tip_amount' => 0,
            'gateway' => null,
            'gateway_intent_id' => null,
            'gateway_status' => null,
            'requires_3ds' => false,
            'failure_reason' => null,
            'taken_by' => null,
            'paid_at' => null,
            'refunded_amount' => 0,
            'refunded_at' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function failed(string $reason = 'Payment failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => $reason,
            'paid_at' => null,
        ]);
    }

    public function refunded(float $amount = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_amount' => $amount,
            'refunded_at' => now(),
        ]);
    }

    public function takenBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'taken_by' => $user->id,
        ]);
    }

    public function withTip(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'tip_amount' => $amount,
        ]);
    }

    public function withGateway(
        string $gateway,
        ?string $intentId = null
    ): static {
        return $this->state(fn (array $attributes) => [
            'gateway' => $gateway,
            'gateway_intent_id' => $intentId,
        ]);
    }

    public function requires3ds(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_3ds' => true,
        ]);
    }
}
