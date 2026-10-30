<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Support\OrderCodeGenerator;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_multiple_requests_produce_distinct_codes(): void
    {
        $restaurant = Restaurant::factory()->create();

        $generator = app(OrderCodeGenerator::class);

        $codes = [
            $generator->generate($restaurant->id),
            $generator->generate($restaurant->id),
            $generator->generate($restaurant->id),
        ];

        $this->assertCount(3, array_unique($codes));
    }

    public function test_sequence_row_has_unique_restaurant_and_service_day(): void
    {
        $restaurant = Restaurant::factory()->create();
        $date = now()->toDateString();

        DB::table('order_sequences')->insert([
            'restaurant_id' => $restaurant->id,
            'service_date' => $date,
            'next_number' => 1040,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('order_sequences')->insert([
            'restaurant_id' => $restaurant->id,
            'service_date' => $date,
            'next_number' => 1041,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_skips_codes_already_used_that_day_without_a_sequence_row(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['#A-1040', '#A-1041', '#A-1044'] as $code) {
            Order::factory()->for($restaurant)->create([
                'service_date' => now()->toDateString(),
                'code' => $code,
            ]);
        }

        $generator = app(OrderCodeGenerator::class);

        $this->assertSame('#A-1045', $generator->generate($restaurant->id));
        $this->assertSame('#A-1046', $generator->generate($restaurant->id));
    }

    public function test_catches_up_when_the_sequence_row_is_behind_existing_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        $generator = app(OrderCodeGenerator::class);

        $this->assertSame('#A-1040', $generator->generate($restaurant->id));

        Order::factory()->for($restaurant)->create([
            'service_date' => now()->toDateString(),
            'code' => '#A-1050',
        ]);

        $this->assertSame('#A-1051', $generator->generate($restaurant->id));
    }

    public function test_the_first_guest_order_after_seeding_the_demo_does_not_collide(): void
    {
        $this->seed([
            RolesSeeder::class,
            AlBustanSeeder::class,
        ]);

        $restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();

        $this->assertSame(
            '#A-1047',
            app(OrderCodeGenerator::class)->generate($restaurant->id),
        );
    }
}
