<?php

use App\Models\MenuItem;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
it('renders the landing page for the restaurant passed through the route', function (): void {
    $firstRestaurant = Restaurant::factory()->create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $secondRestaurant = Restaurant::factory()->create([
        'name' => 'Luna Restaurant',
        'slug' => 'luna-restaurant',
        'timezone' => 'Europe/Istanbul',
    ]);

    $response = $this->get(
        route('restaurants.show', [
            'restaurant' => $secondRestaurant->slug,
        ]),
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/landing')
            ->where('restaurant.id', $secondRestaurant->id)
            ->where('restaurant.name', 'Luna Restaurant')
            ->where('restaurant.slug', 'luna-restaurant')
            ->where(
                'restaurant.id',
                fn ($id) => $id !== $firstRestaurant->id,
            ),
    );
});
it('renders the public landing page for guests', function (): void {
    Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/landing')
            ->has('restaurant')
            ->has('hours')
            ->has('featured_items')
            ->has('open_now')
            ->has('translations')
            ->where('translations.open_now', 'Open now')
            ->where('translations.closed', 'Closed')
            ->where(
                'translations.scan',
                'Scan the code on your table',
            ),
    );
});

it('renders arabic landing translations for the arabic locale', function (): void {
    Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $response = $this
        ->withSession(['locale' => 'ar'])
        ->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/landing')
            ->where('locale', 'ar')
            ->where('dir', 'rtl')
            ->where('translations.open_now', 'مفتوح الآن')
            ->where('translations.closed', 'مغلق')
            ->where(
                'translations.scan',
                'امسح الرمز الموجود على طاولتك',
            )
            ->where('translations.book', 'احجز طاولة')
            ->where('translations.room', 'المكان')
            ->where('translations.find_us', 'موقعنا'),
    );
});

it('marks the restaurant as open during seeded hours', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $restaurant->hours()->create([
        'day_of_week' => 1,
        'opens_at' => '17:00',
        'closes_at' => '23:00',
    ]);

    $this->travelTo(
        Carbon::create(
            2026,
            9,
            14,
            19,
            0,
            0,
            $restaurant->timezone,
        ),
    );

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('open_now', true),
    );
});

it('marks the restaurant as closed outside opening hours', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $restaurant->hours()->create([
        'day_of_week' => 1,
        'opens_at' => '17:00',
        'closes_at' => '23:00',
    ]);

    $this->travelTo(
        Carbon::create(
            2026,
            9,
            14,
            12,
            0,
            0,
            $restaurant->timezone,
        ),
    );

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('open_now', false),
    );
});

it('excludes sold out items from featured items', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $soldOut = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_available' => false,
        'chef_flag' => true,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_available' => true,
        'chef_flag' => true,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where(
                'featured_items',
                fn ($items) => collect($items)
                    ->doesntContain(
                        fn ($item) => $item['id'] === $soldOut->id,
                    ),
            ),
    );
});

it('returns hours in the restaurants timezone', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $restaurant->hours()->create([
        'day_of_week' => 0,
        'opens_at' => '12:00',
        'closes_at' => '22:00',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where(
                'restaurant.timezone',
                'America/New_York',
            )
            ->has('hours', 1)
            ->where('hours.0.day_of_week', 0)
            ->where('hours.0.opens_at', '12:00')
            ->where('hours.0.closes_at', '22:00'),
    );
});
it('keeps featured item translations separate between locale caches', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Kofta',
        'translations' => [
            'en' => 'Kofta',
            'ar' => 'كفتة',
        ],
        'is_available' => true,
        'chef_flag' => true,
    ]);

    $englishResponse = $this
        ->withSession(['locale' => 'en'])
        ->get(route('home'));

    $englishResponse->assertOk();

    $englishResponse->assertInertia(
        fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('featured_items.0.name', 'Kofta'),
    );

    $arabicResponse = $this
        ->withSession(['locale' => 'ar'])
        ->get(route('home'));

    $arabicResponse->assertOk();

    $arabicResponse->assertInertia(
        fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->where('featured_items.0.name', 'كفتة'),
    );
});
it('invalidates the landing cache when a menu item is deleted', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $firstItem = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Kofta',
        'is_available' => true,
        'chef_flag' => true,
        'sort_order' => 1,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Hummus',
        'is_available' => true,
        'sort_order' => 2,
    ]);

    $firstResponse = $this->get(route('home'));

    $firstResponse->assertOk();

    $firstResponse->assertInertia(
        fn (Assert $page) => $page
            ->has('featured_items', 2),
    );

    $firstItem->delete();

    $secondResponse = $this->get(route('home'));

    $secondResponse->assertOk();

    $secondResponse->assertInertia(
        fn (Assert $page) => $page
            ->has('featured_items', 1)
            ->where(
                'featured_items',
                fn ($items) => collect($items)
                    ->doesntContain(
                        fn ($item) => $item['id'] === $firstItem->id,
                    ),
            ),
    );
});
it('marks today using the restaurant timezone instead of the server timezone', function (): void {
    config()->set('app.timezone', 'UTC');
    date_default_timezone_set('UTC');

    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $restaurant->hours()->createMany([
        [
            'day_of_week' => 0,
            'opens_at' => '12:00',
            'closes_at' => '22:00',
        ],
        [
            'day_of_week' => 1,
            'opens_at' => '17:00',
            'closes_at' => '23:00',
        ],
    ]);

    $this->travelTo(
        Carbon::create(
            2026,
            9,
            14,
            2,
            0,
            0,
            'UTC',
        ),
    );

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('restaurant.timezone', 'America/New_York')
            ->has('hours', 2)
            ->where('hours.0.day_of_week', 0)
            ->where('hours.0.is_today', true)
            ->where('hours.1.day_of_week', 1)
            ->where('hours.1.is_today', false),
    );
});
it('returns one chefs pick and one additional featured item', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $chefPick = MenuItem::factory()
        ->forRestaurant($restaurant)
        ->chefsPick()
        ->create([
            'name' => 'Chef Kofta',
            'sort_order' => 1,
        ]);

    $secondChefPick = MenuItem::factory()
        ->forRestaurant($restaurant)
        ->chefsPick()
        ->create([
            'name' => 'Chef Lamb',
            'sort_order' => 2,
        ]);

    $regularItem = MenuItem::factory()
        ->forRestaurant($restaurant)
        ->create([
            'name' => 'Hummus',
            'sort_order' => 3,
        ]);

    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->has('featured_items', 2)
            ->where('featured_items.0.id', $chefPick->id)
            ->where('featured_items.0.chef_flag', true)
            ->where('featured_items.1.id', $regularItem->id)
            ->where('featured_items.1.chef_flag', false)
            ->where(
                'featured_items',
                fn ($items) => collect($items)
                    ->doesntContain(
                        fn ($item) => $item['id'] === $secondChefPick->id,
                    ),
            ),
    );
});
it('invalidates the landing cache when restaurant hours change', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'timezone' => 'America/New_York',
    ]);

    $hour = $restaurant->hours()->create([
        'day_of_week' => 1,
        'opens_at' => '17:00',
        'closes_at' => '23:00',
    ]);

    $firstResponse = $this->get(route('home'));

    $firstResponse->assertOk();

    $firstResponse->assertInertia(
        fn (Assert $page) => $page
            ->has('hours', 1)
            ->where('hours.0.opens_at', '17:00')
            ->where('hours.0.closes_at', '23:00'),
    );

    $hour->update([
        'opens_at' => '18:00',
        'closes_at' => '22:00',
    ]);

    $secondResponse = $this->get(route('home'));

    $secondResponse->assertOk();

    $secondResponse->assertInertia(
        fn (Assert $page) => $page
            ->has('hours', 1)
            ->where('hours.0.opens_at', '18:00')
            ->where('hours.0.closes_at', '22:00'),
    );
});
