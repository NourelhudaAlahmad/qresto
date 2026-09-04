<?php

namespace Tests\Feature;

use App\Models\OrderLine;
use App\Models\OrderLineOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLineOptionFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_line_option_factory_creates_valid_option(): void
    {
        $option = OrderLineOption::factory()->create();

        $this->assertInstanceOf(OrderLineOption::class, $option);
        $this->assertNotNull($option->order_line_id);
        $this->assertContains($option->kind, [
            'variant',
            'addon',
        ]);
        $this->assertNotEmpty($option->label_snapshot);
    }

    public function test_variant_state_creates_variant_option(): void
    {
        $option = OrderLineOption::factory()
            ->variant()
            ->create();

        $this->assertSame('variant', $option->kind);
    }

    public function test_addon_state_creates_addon_option(): void
    {
        $option = OrderLineOption::factory()
            ->addon()
            ->create();

        $this->assertSame('addon', $option->kind);
    }

    public function test_option_can_be_created_for_specific_order_line(): void
    {
        $orderLine = OrderLine::factory()->create();

        $option = OrderLineOption::factory()
            ->forOrderLine($orderLine)
            ->create();

        $this->assertEquals($orderLine->id, $option->order_line_id);
    }

    public function test_free_option_has_zero_price_delta(): void
    {
        $option = OrderLineOption::factory()
            ->free()
            ->create();

        $this->assertEquals(0, (float) $option->price_delta);
    }

    public function test_price_delta_state_sets_price_correctly(): void
    {
        $option = OrderLineOption::factory()
            ->priceDelta(3.50)
            ->create();

        $this->assertEquals(3.50, (float) $option->price_delta);
    }
}
