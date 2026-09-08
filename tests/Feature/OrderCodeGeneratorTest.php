<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Support\OrderCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_codes_for_same_restaurant_and_service_day(): void
    {
        $restaurant = Restaurant::factory()->create();

        $generator = app(OrderCodeGenerator::class);

        $this->assertSame(
            '#A-1040',
            $generator->generate($restaurant->id),
        );

        $this->assertSame(
            '#A-1041',
            $generator->generate($restaurant->id),
        );

        $this->assertSame(
            '#A-1042',
            $generator->generate($restaurant->id),
        );
    }

    public function test_each_restaurant_has_its_own_sequence(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $generator = app(OrderCodeGenerator::class);

        $this->assertSame(
            '#A-1040',
            $generator->generate($restaurantA->id),
        );

        $this->assertSame(
            '#A-1040',
            $generator->generate($restaurantB->id),
        );

        $this->assertSame(
            '#A-1041',
            $generator->generate($restaurantA->id),
        );
    }

    public function test_different_service_days_have_independent_sequences(): void
    {
        $restaurant = Restaurant::factory()->create();

        $generator = app(OrderCodeGenerator::class);

        $dayOne = now()->startOfDay();
        $dayTwo = now()->addDay()->startOfDay();

        $this->assertSame(
            '#A-1040',
            $generator->generate($restaurant->id, $dayOne),
        );

        $this->assertSame(
            '#A-1040',
            $generator->generate($restaurant->id, $dayTwo),
        );

        $this->assertSame(
            '#A-1041',
            $generator->generate($restaurant->id, $dayOne),
        );
    }
public function test_generates_distinct_codes_for_multiple_consecutive_requests(): void
{
    $restaurant = Restaurant::factory()->create();

    $generator = app(OrderCodeGenerator::class);

    $codes = [
        $generator->generate($restaurant->id),
        $generator->generate($restaurant->id),
    ];

    $this->assertCount(2, array_unique($codes));

    $this->assertSame(
        '#A-1040',
        $codes[0],
    );

    $this->assertSame(
        '#A-1041',
        $codes[1],
    );
}
}