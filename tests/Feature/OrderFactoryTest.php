<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_factory_creates_placed_order(): void
    {
        $order = Order::factory()->placed()->create();

        $this->assertSame(OrderStatus::PLACED, $order->status);
        $this->assertFalse($order->is_paid);
    }

    public function test_order_factory_creates_paid_order(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertTrue($order->is_paid);
        $this->assertNotNull($order->paid_at);
    }
}
