<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
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
        $this->assertGreaterThan(0, $line->unit_price);
        $this->assertGreaterThan(0, $line->qty);

        $this->assertEquals(
            round((float) $line->unit_price * $line->qty, 2),
            (float) $line->line_total
        );
    }

    public function test_order_line_can_be_created_for_specific_order(): void
    {
        $order = Order::factory()->create();

        $line = OrderLine::factory()
            ->forOrder($order)
            ->create();

        $this->assertEquals($order->id, $line->order_id);
    }

    public function test_order_line_can_be_created_for_specific_menu_item(): void
    {
        $menuItem = MenuItem::factory()->create();

        $line = OrderLine::factory()
            ->forMenuItem($menuItem)
            ->create();

        $this->assertEquals($menuItem->id, $line->menu_item_id);
        $this->assertEquals($menuItem->name, $line->name_snapshot);

        $menuItemPrice = $menuItem->price->amount() / 100;

        $this->assertEquals(
            $menuItemPrice,
            (float) $line->unit_price
        );

        $this->assertEquals(
            round($menuItemPrice * $line->qty, 2),
            (float) $line->line_total
        );
    }

    public function test_order_line_quantity_updates_line_total(): void
    {
        $line = OrderLine::factory()
            ->state([
                'unit_price' => 19.50,
            ])
            ->quantity(2)
            ->create();

        $this->assertEquals(2, $line->qty);
        $this->assertEquals(39.00, (float) $line->line_total);
    }
}
