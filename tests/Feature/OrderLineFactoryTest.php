<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLineFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_line_factory_creates_valid_line(): void
    {
        $line = OrderLine::factory()->create();

        $this->assertInstanceOf(OrderLine::class, $line);
        $this->assertNotNull($line->order_id);
        $this->assertNotNull($line->menu_item_id);
        $this->assertNotEmpty($line->name_snapshot);
        $this->assertInstanceOf(Money::class, $line->unit_price);
        $this->assertGreaterThan(0, $line->unit_price->amount());
        $this->assertGreaterThan(0, $line->qty);

        $this->assertSame(
            $line->unit_price->amount() * $line->qty,
            $line->line_total->amount(),
        );
    }

    public function test_order_line_can_be_created_for_specific_menu_item(): void
    {
        $menuItem = MenuItem::factory()->create();

        $line = OrderLine::factory()
            ->forMenuItem($menuItem)
            ->create();

        $this->assertSame(
            $menuItem->price->amount(),
            $line->unit_price->amount(),
        );

        $this->assertSame(
            $menuItem->price->amount() * $line->qty,
            $line->line_total->amount(),
        );
    }

    public function test_order_line_quantity_updates_line_total(): void
    {
        $line = OrderLine::factory()
            ->quantity(3)
            ->create();

        $this->assertSame(
            $line->unit_price->amount() * 3,
            $line->line_total->amount(),
        );

        $this->assertSame(3, $line->qty);
    }

    public function test_order_line_can_be_created_for_specific_order(): void
    {
        $order = Order::factory()->create();

        $line = OrderLine::factory()
            ->forOrder($order)
            ->create();

        $this->assertSame(
            $order->id,
            $line->order_id,
        );
    }
}
