<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEventFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_event_factory_creates_valid_event(): void
    {
        $event = OrderEvent::factory()->create();

        $this->assertInstanceOf(OrderEvent::class, $event);
        $this->assertNotNull($event->order_id);
        $this->assertNotEmpty($event->from_status);
        $this->assertNotEmpty($event->to_status);

        $this->assertContains($event->from_status, [
            'placed',
            'pending',
            'preparing',
            'ready',
            'served',
            'paid',
            'cancelled',
        ]);

        $this->assertContains($event->to_status, [
            'placed',
            'pending',
            'preparing',
            'ready',
            'served',
            'paid',
            'cancelled',
        ]);

        $this->assertContains($event->actor_kind, [
            'guest',
            'staff',
            'system',
        ]);

        $this->assertNotNull($event->occurred_at);
    }

    public function test_event_can_be_created_for_specific_order(): void
    {
        $order = Order::factory()->create();

        $event = OrderEvent::factory()
            ->forOrder($order)
            ->create();

        $this->assertEquals($order->id, $event->order_id);
    }

    public function test_guest_event_has_no_actor_id(): void
    {
        $event = OrderEvent::factory()
            ->byGuest()
            ->create();

        $this->assertSame('guest', $event->actor_kind);
        $this->assertNull($event->actor_id);
    }

    public function test_system_event_has_no_actor_id(): void
    {
        $event = OrderEvent::factory()
            ->bySystem()
            ->create();

        $this->assertSame('system', $event->actor_kind);
        $this->assertNull($event->actor_id);
    }

    public function test_staff_event_has_staff_actor(): void
    {
        $user = User::factory()->create();

        $event = OrderEvent::factory()
            ->byStaff($user)
            ->create();

        $this->assertSame('staff', $event->actor_kind);
        $this->assertEquals($user->id, $event->actor_id);
    }

    public function test_transition_state_sets_statuses(): void
    {
        $event = OrderEvent::factory()
            ->transition(
                OrderStatus::PENDING,
                OrderStatus::PREPARING
            )
            ->create();

        $this->assertSame('pending', $event->from_status);
        $this->assertSame('preparing', $event->to_status);
    }

    public function test_event_can_store_meta(): void
    {
        $meta = [
            'source' => 'test',
            'note' => 'Order moved to preparing',
        ];

        $event = OrderEvent::factory()
            ->withMeta($meta)
            ->create();

        $this->assertEquals($meta, $event->meta);
    }
}
