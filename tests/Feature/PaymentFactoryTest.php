<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_factory_creates_valid_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertNotNull($payment->order_id);

        $this->assertContains($payment->method, [
            'cash',
            'card',
            'online',
        ]);

        $this->assertSame('pending', $payment->status);

        $this->assertInstanceOf(
            Money::class,
            $payment->amount,
        );

        $this->assertGreaterThan(
            0,
            $payment->amount->amount(),
        );

        $this->assertInstanceOf(
            Money::class,
            $payment->tip_amount,
        );

        $this->assertSame(
            0,
            $payment->tip_amount->amount(),
        );

        $this->assertFalse($payment->requires_3ds);
        $this->assertNull($payment->paid_at);

        $this->assertInstanceOf(
            Money::class,
            $payment->refunded_amount,
        );

        $this->assertSame(
            0,
            $payment->refunded_amount->amount(),
        );
    }

    public function test_payment_can_be_created_for_specific_order(): void
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()
            ->forOrder($order)
            ->create();

        $this->assertEquals(
            $order->id,
            $payment->order_id,
        );
    }

    public function test_paid_state_marks_payment_as_paid(): void
    {
        $payment = Payment::factory()
            ->paid()
            ->create();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_pending_state_keeps_payment_pending(): void
    {
        $payment = Payment::factory()
            ->pending()
            ->create();

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_failed_state_stores_failure_reason(): void
    {
        $payment = Payment::factory()
            ->failed('Card was declined')
            ->create();

        $this->assertSame('failed', $payment->status);
        $this->assertSame(
            'Card was declined',
            $payment->failure_reason,
        );
        $this->assertNull($payment->paid_at);
    }

    public function test_refunded_state_stores_refunded_amount(): void
    {
        $payment = Payment::factory()
            ->refunded(2550)
            ->create();

        $this->assertSame('refunded', $payment->status);

        $this->assertInstanceOf(
            Money::class,
            $payment->refunded_amount,
        );

        $this->assertSame(
            2550,
            $payment->refunded_amount->amount(),
        );

        $this->assertNotNull($payment->refunded_at);
    }

    public function test_payment_can_be_taken_by_staff_user(): void
    {
        $user = User::factory()->create();

        $payment = Payment::factory()
            ->takenBy($user)
            ->create();

        $this->assertEquals(
            $user->id,
            $payment->taken_by,
        );
    }

    public function test_payment_can_have_tip(): void
    {
        $payment = Payment::factory()
            ->withTip(750)
            ->create();

        $this->assertInstanceOf(
            Money::class,
            $payment->tip_amount,
        );

        $this->assertSame(
            750,
            $payment->tip_amount->amount(),
        );
    }

    public function test_payment_can_use_gateway(): void
    {
        $payment = Payment::factory()
            ->withGateway('stripe', 'pi_test_123')
            ->create();

        $this->assertSame(
            'stripe',
            $payment->gateway,
        );

        $this->assertSame(
            'pi_test_123',
            $payment->gateway_intent_id,
        );
    }

    public function test_payment_can_require_3ds(): void
    {
        $payment = Payment::factory()
            ->requires3ds()
            ->create();

        $this->assertTrue($payment->requires_3ds);
    }

    public function test_payment_uses_parent_order_currency(): void
    {
        $order = Order::factory()->create([
            'currency' => 'USD',
        ]);

        $payment = Payment::factory()
            ->forOrder($order)
            ->create([
                'amount' => 2550,
                'tip_amount' => 750,
                'refunded_amount' => 500,
            ]);

        $this->assertSame(
            'USD',
            $payment->amount->currency(),
        );

        $this->assertSame(
            'USD',
            $payment->tip_amount->currency(),
        );

        $this->assertSame(
            'USD',
            $payment->refunded_amount->currency(),
        );

        $this->assertSame(
            2550,
            $payment->amount->amount(),
        );

        $this->assertSame(
            750,
            $payment->tip_amount->amount(),
        );

        $this->assertSame(
            500,
            $payment->refunded_amount->amount(),
        );
    }
}
