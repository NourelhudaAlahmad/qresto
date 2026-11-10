<?php

use App\Enums\OrderStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\Shift;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->travelTo(now()->setTime(13, 0));

    $this->restaurant = Restaurant::factory()->create(['timezone' => 'UTC']);

    $this->manager = overviewStaff($this->restaurant, 'manager', 'Sara Malik');
});

function overviewStaff(Restaurant $restaurant, string $role, string $name): User
{
    $user = User::factory()->create([
        'name' => $name,
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function overviewOrder(Restaurant $restaurant, array $attributes): Order
{
    return Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        ...$attributes,
    ]);
}

/**
 * Poll the overview the way the page does: an Inertia partial reload.
 */
function pollOverview(User $manager, string $only): TestResponse
{
    return test()
        ->actingAs($manager)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'staff/manager/overview',
            'X-Inertia-Partial-Data' => $only,
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        ])
        ->get('/manager/overview');
}

/**
 * @return list<string>
 */
function loggedQueries(callable $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $request();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $query): string => strtolower($query))
        ->all();

    DB::disableQueryLog();

    return $queries;
}

/**
 * A small floor: two occupied tables, one free, a sitting from last
 * week, two kitchen tickets with prep times and a pair of bill calls.
 */
function seedService(Restaurant $restaurant, User $waiter): void
{
    $occupied = RestaurantTable::factory()->forRestaurant($restaurant)->create(['number' => '01', 'state' => 'ordered', 'sort_order' => 1]);
    $seated = RestaurantTable::factory()->forRestaurant($restaurant)->create(['number' => '02', 'state' => 'seated', 'sort_order' => 2]);
    $free = RestaurantTable::factory()->forRestaurant($restaurant)->create(['number' => '03', 'state' => 'free', 'sort_order' => 3]);

    $party = TableSession::factory()->forTable($occupied)->create(['party_size' => 4]);
    $couple = TableSession::factory()->forTable($seated)->create(['party_size' => 2]);
    $lastWeekParty = TableSession::factory()->forTable($free)->closed()->create(['party_size' => 3]);

    $paid = overviewOrder($restaurant, [
        'table_id' => $occupied->id,
        'table_session_id' => $party->id,
        'assigned_user_id' => $waiter->id,
        'status' => OrderStatus::PAID,
        'is_paid' => true,
        'paid_at' => now(),
        'placed_at' => now()->subMinutes(50),
        'subtotal' => 5000,
        'discount_amount' => 500,
        'total' => 4500,
    ]);

    $late = overviewOrder($restaurant, [
        'table_id' => $occupied->id,
        'table_session_id' => $party->id,
        'assigned_user_id' => $waiter->id,
        'status' => OrderStatus::SERVED,
        'placed_at' => now()->subMinutes(40),
        'subtotal' => 3000,
        'total' => 3000,
    ]);

    overviewOrder($restaurant, [
        'table_id' => $seated->id,
        'table_session_id' => $couple->id,
        'status' => OrderStatus::PREPARING,
        'placed_at' => now()->subMinutes(5),
        'subtotal' => 2000,
        'total' => 2000,
    ]);

    // Unpaid, but on a free table: not part of the unpaid card.
    overviewOrder($restaurant, [
        'table_id' => $free->id,
        'status' => OrderStatus::PLACED,
        'placed_at' => now(),
        'subtotal' => 999,
        'total' => 999,
    ]);

    // Cancelled: never sales, never covers.
    overviewOrder($restaurant, [
        'table_id' => $seated->id,
        'table_session_id' => $couple->id,
        'status' => OrderStatus::CANCELLED,
        'is_paid' => true,
        'subtotal' => 7777,
        'total' => 7777,
    ]);

    overviewOrder($restaurant, [
        'table_id' => $free->id,
        'table_session_id' => $lastWeekParty->id,
        'status' => OrderStatus::PAID,
        'is_paid' => true,
        'placed_at' => now()->subWeek(),
        'service_date' => now()->subWeek()->toDateString(),
        'subtotal' => 3000,
        'total' => 3000,
    ]);

    foreach ([[$paid, 10], [$late, 20]] as [$order, $prepMinutes]) {
        $start = $order->placed_at->copy()->addMinutes(2);

        $order->events()->create(['from_status' => 'pending', 'to_status' => OrderStatus::PREPARING->value, 'actor_kind' => 'staff', 'occurred_at' => $start]);
        $order->events()->create(['from_status' => 'preparing', 'to_status' => OrderStatus::READY->value, 'actor_kind' => 'staff', 'occurred_at' => $start->copy()->addMinutes($prepMinutes)]);
    }

    ServiceRequest::factory()->forTable($occupied)->bill()->create(['requested_at' => now()->subMinutes(12)]);
    ServiceRequest::factory()->forTable($occupied)->bill()->create(['requested_at' => now()->subMinutes(5)]);
    ServiceRequest::factory()->forTable($free)->bill()->create(['requested_at' => now()->subMinutes(30)]);
    ServiceRequest::factory()->forTable($seated)->water()->create(['requested_at' => now()->subMinutes(20)]);
}

it('reports the service-day stats', function () {
    $waiter = overviewStaff($this->restaurant, 'waiter', 'Nadia Rahman');
    seedService($this->restaurant, $waiter);

    $stats = pollOverview($this->manager, 'stats')->assertOk()->json('props.stats');

    expect($stats)->toBe([
        'covers' => 6,
        'covers_last_week' => 3,
        'net_sales_minor' => 4500,
        'net_sales_last_week_minor' => 3000,
        'avg_per_cover_minor' => 750,
        'avg_per_cover_last_week_minor' => 1000,
        'median_prep_minutes' => 15,
        'late_orders_count' => 1,
        'unpaid_total_minor' => 5000,
        'open_bills' => 1,
        'oldest_bill_minutes' => 12,
    ]);
});

/**
 * A waiter on shift now, holding the given tables.
 */
function onShift(User $waiter, RestaurantTable ...$tables): void
{
    $shift = Shift::query()->create([
        'restaurant_id' => $waiter->restaurant_id,
        'user_id' => $waiter->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(4),
    ]);

    foreach ($tables as $table) {
        DB::table('table_user')->insert([
            'shift_id' => $shift->id,
            'user_id' => $waiter->id,
            'table_id' => $table->id,
        ]);
    }
}

/*
 * Budget for a live poll of stats, orders, staff and now:
 *   7  auth, restaurant and permission loading shared by every staff page
 *   4  stats (covers, one pass over the orders, prep times, bill calls)
 *   3  orders (the list, its tables, its waiters)
 *   5  staff (order counts, team, roles, shifts, shift tables)
 * It was 33 before #54.
 */
const OVERVIEW_POLL_QUERY_BUDGET = 19;

it('polls the live props within a fixed query budget', function () {
    $waiter = overviewStaff($this->restaurant, 'waiter', 'Nadia Rahman');
    seedService($this->restaurant, $waiter);
    onShift($waiter, ...RestaurantTable::query()->get()->all());

    $queries = loggedQueries(fn () => pollOverview($this->manager, 'stats,orders,staff,now')
        ->assertOk()
        ->assertJsonStructure(['props' => ['stats', 'orders', 'staff', 'now']]));

    expect(count($queries))->toBeLessThanOrEqual(OVERVIEW_POLL_QUERY_BUDGET);
});

it('keeps the poll query budget as the service grows', function () {
    $waiter = overviewStaff($this->restaurant, 'waiter', 'Nadia Rahman');
    seedService($this->restaurant, $waiter);
    onShift($waiter, ...RestaurantTable::query()->get()->all());

    // Poll twice and count the second: the first warms the permission
    // and restaurant caches the test process keeps between requests.
    $poll = function (): int {
        pollOverview($this->manager, 'stats,orders,staff,now')->assertOk();

        return count(loggedQueries(
            fn () => pollOverview($this->manager, 'stats,orders,staff,now')->assertOk(),
        ));
    };

    $smallServiceQueries = $poll();

    foreach (range(1, 8) as $index) {
        $colleague = overviewStaff($this->restaurant, 'waiter', "Waiter {$index}");
        $table = RestaurantTable::factory()->forRestaurant($this->restaurant)->create(['number' => (string) (10 + $index), 'state' => 'ordered']);
        $session = TableSession::factory()->forTable($table)->create(['party_size' => 2]);

        onShift($colleague, $table);

        foreach ([OrderStatus::PLACED, OrderStatus::PREPARING, OrderStatus::PAID] as $status) {
            overviewOrder($this->restaurant, [
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'assigned_user_id' => $colleague->id,
                'status' => $status,
                'is_paid' => $status === OrderStatus::PAID,
                'subtotal' => 1500,
                'total' => 1500,
            ]);
        }

        ServiceRequest::factory()->forTable($table)->bill()->create();
    }

    expect($poll())->toBe($smallServiceQueries)
        ->and($smallServiceQueries)->toBeLessThanOrEqual(OVERVIEW_POLL_QUERY_BUDGET);
});

it('does not evaluate the drawer props on a stats poll', function () {
    $waiter = overviewStaff($this->restaurant, 'waiter', 'Nadia Rahman');
    seedService($this->restaurant, $waiter);

    $queries = loggedQueries(fn () => pollOverview($this->manager, 'stats')
        ->assertOk()
        ->assertJsonMissingPath('props.waiters')
        ->assertJsonMissingPath('props.tables'));

    $waitersQuery = 'select "id", "name" from "users"';
    $tablesQuery = 'select "number" from "tables"';

    expect(collect($queries)->filter(fn (string $query): bool => str_starts_with($query, $waitersQuery)))->toBeEmpty()
        ->and(collect($queries)->filter(fn (string $query): bool => str_starts_with($query, $tablesQuery)))->toBeEmpty()
        // Shared auth loading plus the four stats queries; orders and
        // staff are not built either.
        ->and(count($queries))->toBeLessThanOrEqual(11);
});

it('still sends the drawer props on a full visit', function () {
    $waiter = overviewStaff($this->restaurant, 'waiter', 'Nadia Rahman');
    overviewStaff($this->restaurant, 'waiter', 'Adam Idle')->forceFill(['is_active' => false])->save();
    seedService($this->restaurant, $waiter);

    $this->actingAs($this->manager)
        ->get('/manager/overview')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/manager/overview')
            ->where('waiters', [['id' => $waiter->id, 'name' => 'Nadia Rahman']])
            ->where('tables', ['01', '02', '03'])
            ->where('stats.covers', 6)
            ->has('orders', 3)
            ->has('staff', 2));
});
