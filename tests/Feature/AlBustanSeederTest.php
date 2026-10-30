<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
        // Six from the design, plus the runner and cashier of #34.
        ->and(User::whereNotNull('restaurant_id')->count())
        ->toBe(8)
        // Tonight's live orders, from the design; the rest is the
        // three-week history Reports draws (#32).
        ->and(Order::where('code', 'not like', '#H-%')->count())
        ->toBe(7)
        ->and(Order::where('code', 'like', '#H-%')->count())
        ->toBeGreaterThan(200);
});

it('seeds the design order totals', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    expect(Order::where('code', '#A-1043')->value('total')->amount())
        ->toBe(5435)
        ->and(Order::where('code', '#A-1041')->value('total')->amount())
        ->toBe(9220)
        ->and(Order::where('code', '#A-1040')->value('total')->amount())
        ->toBe(4400);
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

it('seeds tonight\'s floor team exactly as the design lists it', function () {
    $this->seed([
        RolesSeeder::class,
        AlBustanSeeder::class,
    ]);

    $tablesOf = fn (string $name): array => DB::table('table_user')
        ->join('users', 'users.id', '=', 'table_user.user_id')
        ->join('tables', 'tables.id', '=', 'table_user.table_id')
        ->where('users.name', $name)
        ->orderBy('tables.number')
        ->pluck('tables.number')
        ->map(fn ($number): string => (string) $number)
        ->all();

    $onShift = fn (string $name): bool => Shift::query()
        ->whereHas('user', fn ($query) => $query->where('name', $name))
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->exists();

    expect($tablesOf('Nadia Rahman'))->toBe(['04', '12'])
        ->and($tablesOf('Omar Faruk'))->toBe(['02'])
        ->and($tablesOf('Lin Wu'))->toBe(['07', '09'])
        ->and($onShift('Nadia Rahman'))->toBeTrue()
        ->and($onShift('Omar Faruk'))->toBeTrue()
        ->and($onShift('Lin Wu'))->toBeFalse()
        ->and($onShift('Sara Malik'))->toBeTrue();

    // Each of tonight's orders sits on a table its waiter is assigned to.
    // (History orders were served on past shifts.)
    foreach (Order::query()->with(['table', 'assignedUser'])->whereNotNull('table_id')->where('code', 'not like', '#H-%')->get() as $order) {
        expect($tablesOf($order->assignedUser->name))->toContain($order->table->number);
    }
});
