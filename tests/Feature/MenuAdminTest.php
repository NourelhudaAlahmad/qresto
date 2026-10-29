<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);

    $this->restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();
    $this->yusuf = User::role('admin')->where('restaurant_id', $this->restaurant->id)->firstOrFail();
    $this->bass = MenuItem::query()->where('name', 'Whole sea bass')->firstOrFail();
    $this->kofta = MenuItem::query()->where('name', 'Lamb kofta')->firstOrFail();
});

function guestMenu(Restaurant $restaurant): array
{
    $table = RestaurantTable::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
    $session = TableSession::openFor($table);

    auth()->logout();

    return test()->withCookie('qresto_table_session', $session->token)
        ->get('/menu')
        ->assertOk()
        ->viewData('page')['props']['categories'];
}

/**
 * A real PNG of the given size, built without GD, so the photo rules can be
 * tested on any PHP.
 */
function pngOf(int $width, int $height, string $name = 'photo.png'): UploadedFile
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    $rows = str_repeat("\0".str_repeat("\xC6\x5D\x3B", $width), $height);

    $png = "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        .$chunk('IDAT', (string) gzcompress($rows))
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent($name, $png);
}

function dishPayload(MenuItem $item, array $overrides = []): array
{
    return [
        'menu_category_id' => $item->menu_category_id,
        'name' => $item->name,
        'description' => $item->description,
        'price' => number_format($item->price->amount() / 100, 2, '.', ''),
        'prep_minutes' => $item->prep_minutes,
        ...$overrides,
    ];
}

// ── Availability: the one immediate save ─────────────────────────────

it('toggles availability with no form submit, and the guest menu shows it at once', function () {
    $this->actingAs($this->yusuf)
        ->patch("/app/menu/items/{$this->bass->id}/availability", ['available' => false])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->bass->fresh()->is_available)->toBeFalse()
        ->and($this->bass->fresh()->updated_by)->toBe($this->yusuf->id);

    $bass = collect(guestMenu($this->restaurant))
        ->flatMap(fn ($category) => $category['items'])
        ->firstWhere('name', 'Whole sea bass');

    expect($bass['available'])->toBeFalse();
});

it('changes nothing but availability through that endpoint', function () {
    $price = $this->bass->price->amount();

    $this->actingAs($this->yusuf)
        ->patch("/app/menu/items/{$this->bass->id}/availability", ['available' => false, 'price' => '1.00', 'name' => 'x']);

    expect($this->bass->fresh()->price->amount())->toBe($price)
        ->and($this->bass->fresh()->name)->toBe('Whole sea bass');
});

// ── Categories ───────────────────────────────────────────────────────

it('persists a category reorder and the guest menu follows it', function () {
    $ids = MenuCategory::query()->where('restaurant_id', $this->restaurant->id)->orderBy('sort_order')->pluck('id', 'name');

    $sides = $ids['Sides'];
    $grill = $ids['From the grill'];

    $order = $ids->values()->all();
    $order = array_values(array_diff($order, [$sides]));
    array_splice($order, array_search($grill, $order, true), 0, [$sides]);

    $this->actingAs($this->yusuf)
        ->put('/app/menu/categories/order', ['ids' => $order])
        ->assertSessionHasNoErrors();

    $names = collect(guestMenu($this->restaurant))->pluck('name')->all();

    expect(array_search('Sides', $names, true))->toBeLessThan(array_search('From the grill', $names, true));
});

it('adds, renames and refuses to delete a category that still has dishes', function () {
    $this->actingAs($this->yusuf)->post('/app/menu/categories', ['name' => 'Breakfast'])->assertSessionHasNoErrors();

    $breakfast = MenuCategory::query()->where('name', 'Breakfast')->firstOrFail();

    $this->actingAs($this->yusuf)->patch("/app/menu/categories/{$breakfast->id}", ['name' => 'Brunch'])->assertSessionHasNoErrors();
    expect($breakfast->fresh()->name)->toBe('Brunch');

    $this->actingAs($this->yusuf)
        ->delete("/app/menu/categories/{$this->kofta->menu_category_id}")
        ->assertSessionHasErrors('category');

    $this->actingAs($this->yusuf)->delete("/app/menu/categories/{$breakfast->id}")->assertSessionHasNoErrors();
    expect(MenuCategory::query()->whereKey($breakfast->id)->exists())->toBeFalse();
});

// ── Editing (explicit save) ──────────────────────────────────────────

it('saves a dish edit and stamps who and when', function () {
    $this->actingAs($this->yusuf)
        ->put("/app/menu/items/{$this->kofta->id}", dishPayload($this->kofta, ['price' => '19.50', 'prep_minutes' => 15]))
        ->assertSessionHasNoErrors();

    $kofta = $this->kofta->fresh();

    expect($kofta->price->amount())->toBe(1950)
        ->and($kofta->prep_minutes)->toBe(15)
        ->and($kofta->updated_by)->toBe($this->yusuf->id);

    $this->actingAs($this->yusuf)
        ->get('/app/menu')
        ->assertInertia(fn ($page) => $page
            ->component('staff/admin/menu')
            ->where('dishes', fn ($dishes) => collect($dishes)->firstWhere('id', $kofta->id)['updated_by'] === $this->yusuf->name));
});

it('creates a dish with sizes, add-ons and allergens, then replaces them on save', function () {
    $this->actingAs($this->yusuf)->post('/app/menu/items', [
        'menu_category_id' => $this->kofta->menu_category_id,
        'name' => 'Mixed grill',
        'price' => '32.00',
        'variants' => [
            ['label' => 'For one', 'price_delta' => '0', 'is_default' => true],
            ['label' => 'For two', 'price_delta' => '28.00', 'is_default' => false],
        ],
        'addons' => [['label' => 'Extra toum', 'price_delta' => '1.50', 'is_available' => true]],
        'allergens' => [['name' => 'Sesame', 'may_contain' => false], ['name' => 'Nuts', 'may_contain' => true]],
    ])->assertSessionHasNoErrors();

    $grill = MenuItem::query()->where('name', 'Mixed grill')->firstOrFail();

    expect($grill->variants()->count())->toBe(2)
        ->and($grill->addons()->first()->price_delta->amount())->toBe(150)
        ->and($grill->allergens()->pluck('name')->sort()->values()->all())->toBe(['Nuts', 'Sesame'])
        ->and((bool) $grill->allergens()->where('name', 'Nuts')->first()->pivot->may_contain)->toBeTrue();

    $this->actingAs($this->yusuf)->put("/app/menu/items/{$grill->id}", dishPayload($grill, [
        'variants' => [['id' => $grill->variants()->first()->id, 'label' => 'Single', 'price_delta' => '0', 'is_default' => true]],
        'addons' => [],
        'allergens' => [],
    ]))->assertSessionHasNoErrors();

    expect($grill->variants()->pluck('label')->all())->toBe(['Single'])
        ->and($grill->addons()->count())->toBe(0)
        ->and($grill->allergens()->count())->toBe(0);
});

// ── Photos ───────────────────────────────────────────────────────────

it('rejects an undersized or wrong-ratio photo', function (int $width, int $height) {
    Storage::fake('public');

    $this->actingAs($this->yusuf)
        ->put("/app/menu/items/{$this->kofta->id}", dishPayload($this->kofta, [
            'photo' => pngOf($width, $height),
        ]))
        ->assertSessionHasErrors('photo');

    expect($this->kofta->fresh()->photo_path)->toBeNull();
})->with([
    'undersized 4:3' => [800, 600],
    'wide 16:9' => [1600, 900],
    'square' => [1400, 1400],
]);

it('keeps the original and writes a square thumbnail for list rows', function () {
    Storage::fake('public');

    $this->actingAs($this->yusuf)
        ->put("/app/menu/items/{$this->kofta->id}", dishPayload($this->kofta, [
            'photo' => pngOf(1600, 1200),
        ]))
        ->assertSessionHasNoErrors();

    $kofta = $this->kofta->fresh();

    Storage::disk('public')->assertExists($kofta->photo_path);

    if (! function_exists('imagecreatefromstring')) {
        // No GD on this PHP: the original stands in for the thumbnail.
        expect($kofta->photo_thumb_path)->toBeNull()
            ->and($kofta->thumbUrl())->toBe($kofta->photoUrl());

        return;
    }

    Storage::disk('public')->assertExists($kofta->photo_thumb_path);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($kofta->photo_thumb_path));

    expect($width)->toBe(240)->and($height)->toBe(240);
});

// ── Deleting ─────────────────────────────────────────────────────────

it('refuses to delete a dish on an open order', function () {
    $order = Order::factory()->for($this->restaurant)->preparing()->create();
    OrderLine::factory()->forOrder($order)->forMenuItem($this->kofta)->create(['name_snapshot' => 'Lamb kofta']);

    $this->actingAs($this->yusuf)
        ->delete("/app/menu/items/{$this->kofta->id}")
        ->assertSessionHasErrors('dish');

    expect(MenuItem::query()->whereKey($this->kofta->id)->exists())->toBeTrue();
});

it('soft-deletes a dish that is only on paid orders and keeps those lines readable', function () {
    // Lamb kofta is on #A-1043, which is still open in the demo; use a
    // dish that only ever appeared on a paid order.
    $special = MenuItem::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'menu_category_id' => $this->kofta->menu_category_id,
        'name' => 'Lamb shoulder special',
        'price' => '34.00',
    ]);

    $paid = Order::factory()->for($this->restaurant)->create(['status' => 'paid', 'is_paid' => true]);
    $line = OrderLine::factory()->forOrder($paid)->forMenuItem($special)->create(['name_snapshot' => 'Lamb shoulder special']);

    $this->actingAs($this->yusuf)
        ->delete("/app/menu/items/{$special->id}")
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted('menu_items', ['id' => $special->id]);

    expect($line->fresh()->name_snapshot)->toBe('Lamb shoulder special')
        ->and($line->fresh()->menu_item_id)->toBe($special->id);

    $names = collect(guestMenu($this->restaurant))->flatMap(fn ($category) => $category['items'])->pluck('name');

    expect($names)->not->toContain('Lamb shoulder special');
});

// ── Access ───────────────────────────────────────────────────────────

it('denies a manager without the capability on every route', function () {
    $sara = User::role('manager')->where('restaurant_id', $this->restaurant->id)->firstOrFail();
    $category = $this->kofta->menu_category_id;

    $this->actingAs($sara)->get('/app/menu')->assertForbidden();
    $this->actingAs($sara)->post('/app/menu/categories', ['name' => 'x'])->assertForbidden();
    $this->actingAs($sara)->put('/app/menu/categories/order', ['ids' => [$category]])->assertForbidden();
    $this->actingAs($sara)->patch("/app/menu/categories/{$category}", ['name' => 'x'])->assertForbidden();
    $this->actingAs($sara)->delete("/app/menu/categories/{$category}")->assertForbidden();
    $this->actingAs($sara)->post('/app/menu/items', dishPayload($this->kofta))->assertForbidden();
    $this->actingAs($sara)->put("/app/menu/items/{$this->kofta->id}", dishPayload($this->kofta))->assertForbidden();
    $this->actingAs($sara)->patch("/app/menu/items/{$this->kofta->id}/availability", ['available' => false])->assertForbidden();
    $this->actingAs($sara)->delete("/app/menu/items/{$this->kofta->id}")->assertForbidden();

    expect($this->kofta->fresh()->is_available)->toBeTrue();
});

it('shows the design subtitle with live counts', function () {
    $this->bass->forceFill(['is_available' => false])->save();

    $this->actingAs($this->yusuf)
        ->get('/app/menu')
        ->assertInertia(fn ($page) => $page->where(
            'staffLayout.pageSubtitle',
            fn ($subtitle) => (bool) preg_match('/^\d+ categories · \d+ dishes · [1-9]\d* sold out$/', $subtitle),
        ));
});
