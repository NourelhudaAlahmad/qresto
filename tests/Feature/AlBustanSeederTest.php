<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the Al Bustan design fixtures', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    expect(Restaurant::count())
        ->toBe(1)
        ->and(MenuCategory::count())
        ->toBe(5)
        ->and(MenuItem::count())
        ->toBe(11)
        ->and(RestaurantTable::count())
        ->toBe(12)
        ->and(User::whereNotNull('restaurant_id')->count())
        ->toBe(6)
        ->and(Order::count())
        ->toBe(5);
});

it('seeds the design order totals', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    expect((float) Order::where('code', '#A-1043')->value('total'))
        ->toBe(54.35)
        ->and((float) Order::where('code', '#A-1041')->value('total'))
        ->toBe(92.20)
        ->and((float) Order::where('code', '#A-1040')->value('total'))
        ->toBe(44.00);
});

it('seeds the design table states', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    expect(RestaurantTable::where('state', 'free')->count())
        ->toBe(6)
        ->and(RestaurantTable::where('state', 'seated')->count())
        ->toBe(3)
        ->and(RestaurantTable::where('state', 'ordered')->count())
        ->toBe(2)
        ->and(RestaurantTable::where('state', 'bill')->count())
        ->toBe(1);
});

it('is idempotent when seeded twice', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    $counts = [
        'restaurants' => Restaurant::count(),
        'categories' => MenuCategory::count(),
        'menu_items' => MenuItem::count(),
        'tables' => RestaurantTable::count(),
        'staff' => User::whereNotNull('restaurant_id')->count(),
        'orders' => Order::count(),
    ];

    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    expect(Restaurant::count())
        ->toBe($counts['restaurants'])
        ->and(MenuCategory::count())
        ->toBe($counts['categories'])
        ->and(MenuItem::count())
        ->toBe($counts['menu_items'])
        ->and(RestaurantTable::count())
        ->toBe($counts['tables'])
        ->and(User::whereNotNull('restaurant_id')->count())
        ->toBe($counts['staff'])
        ->and(Order::count())
        ->toBe($counts['orders']);
});
