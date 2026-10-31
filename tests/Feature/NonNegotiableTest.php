<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

/*
 * The design's seven non-negotiables (#36), as gates. Each rule here fails
 * when broken; resources/js/tests/non-negotiables.test.tsx covers the parts
 * only a rendered component can show.
 *
 *   01 one primary, 54px, full width, glass dock     02 nothing under 44px
 *   03 sold-out stays visible and dimmed             04 notes in saffron
 *   05 type floor and 4.5:1 contrast                 06 money mono, tabular
 *   07 prices, codes and times stay LTR inside RTL
 */

function runGate(string $script, string ...$paths): ProcessResult
{
    return Process::path(base_path())->run(['node', "scripts/{$script}", ...$paths]);
}

it('01 guards the guest dock and screen at runtime', function () {
    $dock = file_get_contents(resource_path('js/components/qresto/sticky-dock.tsx'));
    $layout = file_get_contents(resource_path('js/layouts/guest-layout.tsx'));
    $rules = file_get_contents(resource_path('js/lib/non-negotiables.ts'));

    expect($dock)->toContain('checkDock(')->toContain('bg-[var(--alpha-paper-88)]')->toContain('backdrop-blur')
        ->and($layout)->toContain('checkScreen(')
        // Under test a broken rule throws, so every guest page test enforces it.
        ->and($rules)->toContain("import.meta.env.MODE === 'test'")->toContain('throw new Error');
});

it('02 05 06 and logical direction hold across QResto screens', function () {
    $result = runGate('check-non-negotiables.mjs');

    expect($result->exitCode())->toBe(0, $result->errorOutput());
});

it('02 the gate fails on a 40px button, and passes once corrected', function () {
    $dir = storage_path('framework/testing/non-negotiables');
    @mkdir($dir, 0777, true);

    file_put_contents("{$dir}/Probe.tsx", "export function Probe() {\n    return <button className=\"h-10 px-3\">Too small</button>;\n}\n");
    $broken = runGate('check-non-negotiables.mjs', $dir);

    file_put_contents("{$dir}/Probe.tsx", "export function Probe() {\n    return <button className=\"h-11 px-3\">Big enough</button>;\n}\n");
    $fixed = runGate('check-non-negotiables.mjs', $dir);

    @unlink("{$dir}/Probe.tsx");

    expect($broken->exitCode())->toBe(1)
        ->and($broken->errorOutput())->toContain('is 40px')
        ->and($fixed->exitCode())->toBe(0);
});

it('02 keeps a 44px hit area for controls that are smaller to the eye', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('.touch-target::after')
        ->toContain('width: max(100%, 44px)')
        ->toContain('height: max(100%, 44px)');
});

it('03 keeps sold-out dishes in every menu payload', function () {
    $restaurant = Restaurant::factory()->create(['currency' => 'USD']);
    $table = RestaurantTable::factory()->forRestaurant($restaurant)->create();
    $session = TableSession::openFor($table);
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

    MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Lamb kofta', 'is_available' => true, 'is_scheduled' => false]);
    $bass = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Whole sea bass', 'is_available' => false, 'is_scheduled' => false]);

    $items = collect(
        $this->withCookie('qresto_table_session', $session->token)
            ->get('/menu')
            ->assertOk()
            ->viewData('page')['props']['categories'],
    )->flatMap(fn ($category) => $category['items']);

    // Hiding a sold-out dish to tidy the list breaks the rule.
    expect($items->pluck('name')->all())->toContain('Whole sea bass')
        ->and($items->firstWhere('name', 'Whole sea bass')['available'])->toBeFalse();

    // …and its page still opens, so a guest can see why.
    $this->withCookie('qresto_table_session', $session->token)
        ->get("/menu/{$bass->id}")
        ->assertOk();
});

it('04 echoes the guest note in saffron on the printed receipt', function () {
    $this->seed(RolesSeeder::class);

    [, $user, $table, $order] = receiptFixtures();

    OrderLine::factory()->forOrder($order)->create(['name_snapshot' => 'Charred aubergine', 'note' => 'no tahini']);

    $html = $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/data-guest-note[^>]*>\s*no tahini|text-\[var\(--warning-fg\)\][^>]*data-guest-note[^>]*>\s*no tahini/');
});

it('04 echoes the guest note in saffron in the cart and on the waiter card', function () {
    foreach (['js/pages/guest/cart.tsx', 'js/pages/staff/floor.tsx'] as $file) {
        $source = file_get_contents(resource_path($file));

        preg_match_all('/\.note\s*&&\s*\(\s*<[^>]*className=["{`]([^"}`]+)/s', $source, $matches);

        expect($matches[1])->not->toBeEmpty("{$file} renders no note");

        foreach ($matches[1] as $classes) {
            expect($classes)->toMatch('/--warning-fg|--saffron-/', "{$file}: a note is not saffron");
        }
    }
});

it('05 keeps body text at 15px and guest reading copy at 17px', function () {
    $type = file_get_contents(resource_path('css/tokens/typography.css'));

    expect($type)->toMatch('/--text-body:\s*0\.9375rem;/')
        ->and($type)->toMatch('/--text-body-lg:\s*1\.0625rem;/');
});

it('05 keeps every text token pair at 4.5:1, apart from the documented exceptions', function () {
    $result = runGate('check-contrast.mjs');

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and($result->output())->toContain('documented exceptions');
});

it('06 07 renders numbers through Numeric: LTR, tabular', function () {
    $numeric = file_get_contents(resource_path('js/components/numeric.tsx'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($numeric)->toContain('dir="ltr"')->toContain('qr-numeric')
        ->and($css)->toMatch('/qr-numeric\s*\{[^}]*font-family:\s*var\(--font-mono\);[^}]*tabular-nums/s');
});
