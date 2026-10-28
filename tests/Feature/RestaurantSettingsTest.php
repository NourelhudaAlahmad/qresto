<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);

    $this->restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();
    $this->yusuf = User::query()->where('email', 'yusuf.aziz@al-bustan.test')->first()
        ?? User::query()->role('admin')->where('restaurant_id', $this->restaurant->id)->firstOrFail();
});

function detailsPayload(Restaurant $restaurant, array $overrides = []): array
{
    return [
        'name' => $restaurant->name,
        'tagline' => $restaurant->tagline,
        'cuisine' => $restaurant->cuisine,
        'address' => $restaurant->address,
        'phone' => $restaurant->phone,
        'timezone' => $restaurant->timezone,
        'currency' => $restaurant->currency,
        'service_charge_pct' => (float) $restaurant->service_charge_pct,
        'default_locale' => $restaurant->default_locale,
        'supported_locales' => $restaurant->supported_locales,
        ...$overrides,
    ];
}

function weekPayload(array $overrides = []): array
{
    $week = [];

    foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
        $week[] = ['day_of_week' => $day, 'closed' => false, 'opens_at' => '17:00', 'closes_at' => '23:00'];
    }

    foreach ($overrides as $index => $day) {
        $week[$index] = [...$week[$index], ...$day];
    }

    return ['hours' => $week];
}

function tableByNumber(Restaurant $restaurant, string $number): RestaurantTable
{
    return RestaurantTable::withoutGlobalScopes()
        ->where('restaurant_id', $restaurant->id)
        ->where('number', $number)
        ->firstOrFail();
}

// ── Details and service charge ───────────────────────────────────────

it('changes the service charge for new carts and leaves placed orders untouched', function () {
    $placed = Order::query()->where('code', '#A-1043')->firstOrFail();
    $before = (string) $placed->service_pct;

    $this->actingAs($this->yusuf)
        ->put('/app/settings/restaurant', detailsPayload($this->restaurant, ['service_charge_pct' => 10]))
        ->assertSessionHasNoErrors();

    expect((string) $this->restaurant->fresh()->service_charge_pct)->toBe('10.00')
        ->and((string) $placed->fresh()->service_pct)->toBe($before);

    auth()->logout();

    $session = TableSession::openFor(tableByNumber($this->restaurant, '01'));

    $this->withCookie('qresto_table_session', $session->token)
        ->get('/cart')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cart.service_pct', '10.00'));
});

it('validates currency, timezone and languages against the supported lists', function () {
    $this->actingAs($this->yusuf)
        ->put('/app/settings/restaurant', detailsPayload($this->restaurant, [
            'currency' => 'XYZ',
            'timezone' => 'Mars/Olympus',
            'supported_locales' => ['en', 'fr'],
            'default_locale' => 'ar',
        ]))
        ->assertSessionHasErrors(['currency', 'timezone', 'supported_locales.1', 'default_locale']);
});

it('refuses a service charge outside 0–30%', function () {
    $this->actingAs($this->yusuf)
        ->put('/app/settings/restaurant', detailsPayload($this->restaurant, ['service_charge_pct' => 125]))
        ->assertSessionHasErrors('service_charge_pct');
});

// ── Hours ────────────────────────────────────────────────────────────

it('rejects a closing time before the opening time', function () {
    $this->actingAs($this->yusuf)
        ->put('/app/settings/hours', weekPayload([0 => ['opens_at' => '17:00', 'closes_at' => '12:00']]))
        ->assertSessionHasErrors('hours.0.closes_at');
});

it('allows a closed day and a midnight close', function () {
    $this->actingAs($this->yusuf)
        ->put('/app/settings/hours', weekPayload([
            0 => ['closed' => true, 'opens_at' => null, 'closes_at' => null],
            4 => ['opens_at' => '12:00', 'closes_at' => '00:00'],
        ]))
        ->assertSessionHasNoErrors();

    $monday = $this->restaurant->hours()->where('day_of_week', 1)->first();
    $friday = $this->restaurant->hours()->where('day_of_week', 5)->first();

    expect($monday->opens_at)->toBeNull()
        ->and($monday->closes_at)->toBeNull()
        ->and(substr((string) $friday->closes_at, 0, 5))->toBe('00:00');
});

// ── Tables ───────────────────────────────────────────────────────────

it('adds, renames and lists tables', function () {
    $this->actingAs($this->yusuf)
        ->post('/app/settings/tables', ['number' => '13', 'seats' => 6])
        ->assertSessionHasNoErrors();

    $thirteen = tableByNumber($this->restaurant, '13');

    expect($thirteen->seats)->toBe(6)
        ->and(strlen($thirteen->qr_token))->toBe(40);

    $this->actingAs($this->yusuf)
        ->patch("/app/settings/tables/{$thirteen->id}", ['number' => 'Bar 1', 'seats' => 2, 'sort_order' => 50])
        ->assertSessionHasNoErrors();

    expect($thirteen->fresh()->number)->toBe('Bar 1');

    $this->actingAs($this->yusuf)
        ->post('/app/settings/tables', ['number' => '04', 'seats' => 2])
        ->assertSessionHasErrors('number');
});

it('refuses to retire a table with guests seated', function () {
    $table = tableByNumber($this->restaurant, '09');
    TableSession::openFor($table);

    $this->actingAs($this->yusuf)
        ->post("/app/settings/tables/{$table->id}/retire")
        ->assertSessionHasErrors('table');

    expect($table->fresh()->retired_at)->toBeNull();
});

it('refuses to retire a table with an unpaid bill', function () {
    // #A-1041 on Table 07 is served and unpaid in the seed.
    $table = tableByNumber($this->restaurant, '07');

    $this->actingAs($this->yusuf)
        ->post("/app/settings/tables/{$table->id}/retire")
        ->assertSessionHasErrors('table');
});

it('retires a free table: gone from the floor and its sticker stops working, history kept', function () {
    $table = tableByNumber($this->restaurant, '01');
    $token = $table->qr_token;

    $this->actingAs($this->yusuf)
        ->post("/app/settings/tables/{$table->id}/retire")
        ->assertSessionHasNoErrors();

    expect($table->fresh()->retired_at)->not->toBeNull()
        ->and(RestaurantTable::query()->whereKey($table->id)->exists())->toBeFalse();

    auth()->logout();

    $this->get("/t/{$token}")->assertNotFound();

    // Adding the number again brings the table back with a new sticker.
    $this->actingAs($this->yusuf)
        ->post('/app/settings/tables', ['number' => '01', 'seats' => 2])
        ->assertSessionHasNoErrors();

    $back = tableByNumber($this->restaurant, '01');

    expect($back->id)->toBe($table->id)
        ->and($back->retired_at)->toBeNull()
        ->and($back->qr_token)->not->toBe($token);
});

// ── QR codes ─────────────────────────────────────────────────────────

it('reissues a code: the old one 404s at once and its sessions close', function () {
    $table = tableByNumber($this->restaurant, '12');
    $old = $table->qr_token;
    $session = TableSession::openFor($table);

    $this->actingAs($this->yusuf)
        ->post("/app/settings/tables/{$table->id}/reissue")
        ->assertSessionHasNoErrors();

    $new = $table->fresh()->qr_token;

    expect($new)->not->toBe($old)
        ->and($session->fresh()->closed_at)->not->toBeNull();

    auth()->logout();

    $this->get("/t/{$old}")->assertNotFound();
    $this->get("/t/{$new}")->assertOk();
});

it('prints one card per table, each pointing at its own table', function () {
    $response = $this->actingAs($this->yusuf)
        ->get('/app/settings/tables/qr-sheet')
        ->assertOk();

    $html = $response->getContent();

    expect(substr_count($html, 'class="card"'))->toBe(12);

    foreach (RestaurantTable::query()->where('restaurant_id', $this->restaurant->id)->get() as $table) {
        expect($html)->toContain(route('table.show', $table->qr_token))
            ->and($html)->toContain("Table {$table->number}");
    }
});

it('reprints a single table', function () {
    $table = tableByNumber($this->restaurant, '12');

    $html = $this->actingAs($this->yusuf)
        ->get("/app/settings/tables/qr-sheet?table={$table->id}")
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'class="card"'))->toBe(1)
        ->and($html)->toContain(route('table.show', $table->qr_token));
});

it('keeps every QR token when the demo is re-seeded (#44)', function () {
    $before = RestaurantTable::withoutGlobalScopes()->orderBy('number')->pluck('qr_token', 'number')->all();

    $this->seed(AlBustanSeeder::class);

    $after = RestaurantTable::withoutGlobalScopes()->orderBy('number')->pluck('qr_token', 'number')->all();

    expect($after)->toBe($before);
});

// ── Access ───────────────────────────────────────────────────────────

it('keeps managers and waiters out of every settings route', function (string $role) {
    $user = User::query()->role($role)->where('restaurant_id', $this->restaurant->id)->firstOrFail();
    $table = tableByNumber($this->restaurant, '01');

    $this->actingAs($user)->get('/app/settings/restaurant')->assertForbidden();
    $this->actingAs($user)->put('/app/settings/restaurant', detailsPayload($this->restaurant))->assertForbidden();
    $this->actingAs($user)->put('/app/settings/hours', weekPayload())->assertForbidden();
    $this->actingAs($user)->post('/app/settings/tables', ['number' => '99', 'seats' => 2])->assertForbidden();
    $this->actingAs($user)->post("/app/settings/tables/{$table->id}/reissue")->assertForbidden();
    $this->actingAs($user)->get('/app/settings/tables/qr-sheet')->assertForbidden();
})->with(['manager', 'waiter']);

it('renders the settings screen for the admin', function () {
    $this->actingAs($this->yusuf)
        ->get('/app/settings/restaurant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/admin/restaurant')
            ->has('hours', 7)
            ->has('tables', 12)
            ->where('hours.0.day_of_week', 1)
            ->where('restaurant.currency', 'USD'));
});

it('refuses renaming a table onto a retired table\'s number instead of crashing', function () {
    $one = tableByNumber($this->restaurant, '01');
    $two = tableByNumber($this->restaurant, '02');

    $this->actingAs($this->yusuf)->post("/app/settings/tables/{$one->id}/retire");

    $this->actingAs($this->yusuf)
        ->patch("/app/settings/tables/{$two->id}", ['number' => '01', 'seats' => 2])
        ->assertRedirect()
        ->assertSessionHasErrors('number');

    expect($two->fresh()->number)->toBe('02');
});
