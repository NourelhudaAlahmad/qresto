<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Payments\Gateways\FakeGateway;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * One service, end to end (#37). Every other test checks its own slice;
 * this one walks the seams: a guest scans, orders and pays (or doesn't),
 * the kitchen and the floor move the order on, the bill is settled, the
 * table is freed, and the guest's own screen is checked after every staff
 * action. Factories only, never the demo seeder; the clock is travelled,
 * never waited on.
 *
 * Roles: guest, waiter, kitchen, cashier, manager.
 */

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    Carbon::setTestNow(Carbon::parse('2026-10-30 19:00:00', 'UTC'));

    $this->restaurant = Restaurant::factory()->create([
        'currency' => 'USD',
        'timezone' => 'UTC',
        'service_charge_pct' => '10.00',
    ]);

    $this->table = RestaurantTable::factory()->forRestaurant($this->restaurant)->create(['number' => '07']);

    $staff = fn (string $role, string $name) => tap(
        User::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => $name, 'is_active' => true]),
        fn (User $user) => $user->assignRole($role),
    );

    $this->waiter = $staff('waiter', 'Nadia Rahman');
    $this->kitchen = $staff('kitchen', 'Kitchen Pass');
    $this->cashier = $staff('cashier', 'Layla Nasser');
    $this->manager = $staff('manager', 'Sara Malik');

    // Tonight, Table 07 is Nadia's.
    assignTonight($this->waiter, $this->table);

    $grill = MenuCategory::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'name' => 'From the grill',
        'station' => 'grill',
        'is_active' => true,
    ]);

    $this->kofta = MenuItem::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'menu_category_id' => $grill->id,
        'name' => 'Lamb kofta',
        'price' => '18.00',
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $this->large = MenuItemVariant::factory()->create([
        'menu_item_id' => $this->kofta->id,
        'label' => 'Large',
        'price_delta' => '4.00',
        'is_default' => false,
    ]);

    $this->toum = MenuItemAddon::factory()->create([
        'menu_item_id' => $this->kofta->id,
        'label' => 'Extra toum',
        'price_delta' => '1.50',
        'is_available' => true,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * The guest's side of the journey, carrying their table cookie.
 */
final class Guest
{
    public ?string $cookie = null;

    public function __construct(private readonly RestaurantTable $table) {}

    public function request(): TestCase
    {
        $test = test();

        return $this->cookie === null ? $test : $test->withCookie('qresto_table_session', $this->cookie);
    }

    /** Scan the sticker, confirm the table, keep the session cookie. */
    public function scanAndConfirm(string $name): void
    {
        test()->get("/t/{$this->table->qr_token}")->assertOk();

        test()->post("/t/{$this->table->qr_token}/confirm", ['first_name' => $name])->assertRedirect();

        // The cookie the browser keeps holds this session's token.
        $session = TableSession::query()
            ->withoutGlobalScopes()
            ->where('restaurant_table_id', $this->table->id)
            ->whereNull('closed_at')
            ->latest('id')
            ->firstOrFail();

        expect($session->guest_name)->toBe($name);

        $this->cookie = $session->token;
    }

    /** What the guest's status screen says right now. */
    public function status(Order $order): array
    {
        $props = $this->request()
            ->get("/orders/{$order->id}/status")
            ->assertOk()
            ->viewData('page')['props'];

        return $props['live'];
    }
}

/**
 * Scan → confirm → menu → configure a meal → cart → checkout.
 */
function orderAsGuest(object $test, string $method): array
{
    $guest = new Guest($test->table);
    $guest->scanAndConfirm('Rami');

    // Browse the menu: the dish is there.
    $menu = $guest->request()->get('/menu')->assertOk()->viewData('page')['props'];
    expect(collect($menu['categories'])->flatMap(fn ($c) => $c['items'])->pluck('name'))->toContain('Lamb kofta');

    // Configure the meal: large, extra toum, two of them, a note.
    $guest->request()->get("/menu/{$test->kofta->id}")->assertOk();
    $guest->request()->post('/cart/lines', [
        'menu_item_id' => $test->kofta->id,
        'menu_item_variant_id' => $test->large->id,
        'addon_ids' => [$test->toum->id],
        'qty' => 2,
        'note' => 'no onions',
    ])->assertSessionHasNoErrors();

    $cart = $guest->request()->get('/cart')->assertOk()->viewData('page')['props']['cart'];

    // (18.00 + 4.00 + 1.50) × 2 = 47.00
    expect($cart['subtotal']['amount'])->toBe(4700);

    $guest->request()->get('/checkout')->assertOk();

    $payload = [
        'method' => $method,
        'tip_pct' => 0,
        'split_ways' => 1,
        'idempotency_key' => "journey-{$method}",
    ];

    if ($method === 'card') {
        $payload += ['card_number' => FakeGateway::SUCCESS_CARD, 'card_expiry' => '12/30', 'card_cvc' => '123'];
    }

    $guest->request()->post('/checkout', $payload)->assertSessionHasNoErrors();

    $order = Order::query()->where('idempotency_key', "journey-{$method}")->firstOrFail();

    // The cart starts fresh: ordering again cannot repeat these dishes.
    expect($guest->request()->get('/cart')->viewData('page')['props']['cart']['lines'])->toBe([]);

    // The floor shows the table as taken, never free.
    expect($test->table->fresh()->state->value)->toBe('ordered');

    return [$guest, $order];
}

/** The order's event trail: [to_status, actor] in order. */
function trail(Order $order): array
{
    return $order->events()->reorder()->orderBy('id')->get()
        ->filter(fn ($event) => ($event->meta['kind'] ?? null) === null)
        ->map(fn ($event) => [$event->to_status, $event->actor_id])
        ->values()
        ->all();
}

/**
 * The kitchen and the floor take the order from placed to served,
 * asserting the guest's screen after each move.
 */
function serviceThroughToServed(object $test, Guest $guest, Order $order): void
{
    // The waiter accepts the order into the kitchen.
    Carbon::setTestNow(now()->addMinutes(1));
    // The order reached the waiter who has this table tonight.
    expect($order->fresh()->assigned_user_id)->toBe($test->waiter->id);

    test()->actingAs($test->waiter)->patch("/app/orders/{$order->id}/advance")->assertSessionHasNoErrors()->assertRedirect();
    expect($guest->status($order)['status'])->toBe('pending');

    // The pass shows it, and the kitchen bumps it twice.
    $pass = test()->actingAs($test->kitchen)->get('/app/pass')->assertOk()->viewData('page')['props'];
    expect(collect($pass['tickets'])->pluck('code'))->toContain($order->code);

    Carbon::setTestNow(now()->addMinutes(2));
    test()->actingAs($test->kitchen)->post("/app/kds/tickets/{$order->id}/bump")->assertSessionHasNoErrors();
    expect($guest->status($order)['status'])->toBe('preparing');

    Carbon::setTestNow(now()->addMinutes(12));
    test()->actingAs($test->kitchen)->post("/app/kds/tickets/{$order->id}/bump")->assertSessionHasNoErrors();
    expect($guest->status($order)['status'])->toBe('ready');

    // Ready leaves the pass.
    $pass = test()->actingAs($test->kitchen)->get('/app/pass')->viewData('page')['props'];
    expect(collect($pass['tickets'])->pluck('code'))->not->toContain($order->code);

    // The waiter carries it to the table.
    Carbon::setTestNow(now()->addMinutes(1));
    test()->actingAs($test->waiter)->patch("/app/orders/{$order->id}/advance")->assertSessionHasNoErrors();
    expect($guest->status($order)['status'])->toBe('served');
}

/** Who may not do what, at the step where it would matter. */
function assertRolesStayInLane(object $test, Order $order): void
{
    // The kitchen cannot take money…
    test()->actingAs($test->kitchen)
        ->post("/app/tables/{$test->table->id}/payments", ['order_id' => $order->id, 'method' => 'cash'])
        ->assertForbidden();

    // …and the waiter cannot change the menu.
    test()->actingAs($test->waiter)
        ->patch("/app/menu/items/{$test->kofta->id}/availability", ['available' => false])
        ->assertForbidden();

    expect($test->kofta->fresh()->is_available)->toBeTrue();
}

it('runs a full service paid by card now', function () {
    [$guest, $order] = orderAsGuest($this, 'card');

    // Paid at checkout: the kitchen gets it straight away, already paid.
    expect($order->is_paid)->toBeTrue()
        ->and($guest->status($order)['paid'])->toBeTrue();

    assertRolesStayInLane($this, $order);
    serviceThroughToServed($this, $guest, $order);

    // Nothing to settle: the table can be freed.
    $this->actingAs($this->waiter)->post("/app/tables/{$this->table->id}/free")->assertSessionHasNoErrors();
    expect($this->table->fresh()->state->value)->toBe('free');

    expect(array_column(trail($order->fresh()), 0))->toBe(['placed', 'pending', 'preparing', 'ready', 'served']);
});

it('runs a full service with cash, settled by the waiter', function (string $method) {
    [$guest, $order] = orderAsGuest($this, $method);

    // Deferred: unpaid, and the guest sees it so.
    expect($order->is_paid)->toBeFalse()
        ->and($guest->status($order)['paid'])->toBeFalse();

    // The cashier's view lists the unpaid bill.
    $bills = $this->actingAs($this->cashier)->get('/app/orders')->assertOk()->viewData('page')['props']['orders'];
    expect(collect($bills)->pluck('code'))->toContain($order->code);

    assertRolesStayInLane($this, $order);
    serviceThroughToServed($this, $guest, $order);

    // A table with an unpaid bill cannot be freed.
    $this->actingAs($this->waiter)->post("/app/tables/{$this->table->id}/free")->assertSessionHasErrors();
    expect($this->table->fresh()->state->value)->not->toBe('free');

    // The waiter settles at the table.
    Carbon::setTestNow(now()->addMinutes(20));
    $this->actingAs($this->waiter)
        ->post("/app/tables/{$this->table->id}/payments", ['order_id' => $order->id, 'method' => $method])
        ->assertSessionHasNoErrors();

    // The guest's screen flips to Paid on its own: the guest did nothing.
    expect($order->fresh()->is_paid)->toBeTrue()
        ->and($guest->status($order)['paid'])->toBeTrue()
        ->and($guest->status($order)['payment_method'])->toBe($method);

    // Settled: now the table frees, and the bill leaves the cashier's list.
    $this->actingAs($this->waiter)->post("/app/tables/{$this->table->id}/free")->assertSessionHasNoErrors();
    expect($this->table->fresh()->state->value)->toBe('free');

    $bills = $this->actingAs($this->cashier)->get('/app/orders')->viewData('page')['props']['orders'];
    expect(collect($bills)->pluck('code'))->not->toContain($order->code);
})->with([
    'cash' => ['cash'],
    'card at the table' => ['pos'],
]);

it('attributes every step of the trail to the right person', function () {
    [$guest, $order] = orderAsGuest($this, 'cash');

    serviceThroughToServed($this, $guest, $order);

    expect(trail($order->fresh()))->toBe([
        ['placed', null],                 // the guest, from their own phone
        ['pending', $this->waiter->id],   // accepted by the waiter
        ['preparing', $this->kitchen->id],
        ['ready', $this->kitchen->id],
        ['served', $this->waiter->id],
    ]);
});

it('reports exactly the day the journey created', function () {
    foreach (['card', 'cash', 'pos'] as $method) {
        [, $order] = orderAsGuest($this, $method);

        if ($method !== 'card') {
            $this->actingAs($this->waiter)->patch("/app/orders/{$order->id}/advance");
            foreach ([1, 2] as $bump) {
                $this->actingAs($this->kitchen)->post("/app/kds/tickets/{$order->id}/bump");
            }
            $this->actingAs($this->waiter)->patch("/app/orders/{$order->id}/advance");
            $this->actingAs($this->waiter)->post("/app/tables/{$this->table->id}/payments", ['order_id' => $order->id, 'method' => $method]);
        }
    }

    $orders = Order::query()->where('restaurant_id', $this->restaurant->id)->get();

    // Three orders of 47.00 + 10% service = 51.70 each, no tips.
    expect($orders)->toHaveCount(3)
        ->and($orders->every(fn (Order $order) => $order->is_paid))->toBeTrue();

    $totals = $this->actingAs($this->manager)
        ->get('/app/reports?preset=today')
        ->assertOk()
        ->viewData('page')['props']['totals'];

    expect($totals['orders'])->toBe(3)
        ->and($totals['gross_minor'])->toBe($orders->sum(fn (Order $order) => $order->total->amount()))
        ->and($totals['net_minor'])->toBe(3 * 5170)
        ->and($totals['service_minor'])->toBe(3 * 470)
        ->and($totals['refunds_minor'])->toBe(0);
});
