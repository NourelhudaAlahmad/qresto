<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_factory_creates_valid_promotion(): void
    {
        $promotion = Promotion::factory()->create();

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertNotNull($promotion->restaurant_id);
        $this->assertNotEmpty($promotion->code);
        $this->assertContains($promotion->kind, ['percent', 'amount']);
        $this->assertGreaterThan(0, (float) $promotion->value);
        $this->assertNotNull($promotion->starts_at);
        $this->assertNotNull($promotion->ends_at);
        $this->assertSame(0, $promotion->uses);
    }

    public function test_promotion_can_be_created_for_specific_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $promotion = Promotion::factory()
            ->forRestaurant($restaurant)
            ->create();

        $this->assertEquals($restaurant->id, $promotion->restaurant_id);
    }

    public function test_percent_state_creates_percent_promotion(): void
    {
        $promotion = Promotion::factory()
            ->percent(15)
            ->create();

        $this->assertSame('percent', $promotion->kind);
        $this->assertEquals(15, (float) $promotion->value);
    }

    public function test_amount_state_creates_amount_promotion(): void
    {
        $promotion = Promotion::factory()
            ->amount(7.50)
            ->create();

        $this->assertSame('amount', $promotion->kind);
        $this->assertEquals(7.50, (float) $promotion->value);
    }

    public function test_active_state_creates_active_promotion(): void
    {
        $promotion = Promotion::factory()
            ->active()
            ->create();

        $this->assertTrue($promotion->starts_at->isPast());
        $this->assertTrue($promotion->ends_at->isFuture());
    }

    public function test_expired_state_creates_expired_promotion(): void
    {
        $promotion = Promotion::factory()
            ->expired()
            ->create();

        $this->assertTrue($promotion->ends_at->isPast());
    }

    public function test_unlimited_state_removes_maximum_usage_limit(): void
    {
        $promotion = Promotion::factory()
            ->unlimited()
            ->create();

        $this->assertNull($promotion->max_uses);
    }

    public function test_used_state_sets_usage_count(): void
    {
        $promotion = Promotion::factory()
            ->used(25)
            ->create();

        $this->assertSame(25, $promotion->uses);
    }
}
