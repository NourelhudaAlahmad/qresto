<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\ReportExport;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Reports\SalesReport;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    Carbon::setTestNow(Carbon::parse('2026-10-24 21:00:00', 'UTC')); // a Saturday

    $this->restaurant = Restaurant::factory()->create(['timezone' => 'UTC', 'currency' => 'USD']);

    $this->nadia = User::factory()->create(['name' => 'Nadia Rahman', 'restaurant_id' => $this->restaurant->id]);
    $this->nadia->assignRole('waiter');

    $this->sara = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->sara->assignRole('manager');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * One order with one payment. Amounts in minor units; the payment amount
 * includes the tip, as checkout records it.
 */
function sale(Restaurant $restaurant, User $waiter, string $day, int $sales, int $tip = 0, int $refund = 0, string $method = 'card', string $orderStatus = 'paid', string $paymentStatus = 'paid', int $party = 2): Order
{
    $session = TableSession::query()->withoutGlobalScopes()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => RestaurantTable::factory()->forRestaurant($restaurant)->create()->id,
        'token' => Str::random(40),
        'party_size' => $party,
        'opened_at' => now(),
    ]);

    $order = Order::factory()->for($restaurant)->create([
        'assigned_user_id' => $waiter->id,
        'table_session_id' => $session->id,
        'service_date' => $day,
        'status' => $orderStatus,
        'is_paid' => $orderStatus === 'paid',
        'service_amount' => 100,
    ]);

    Payment::factory()->forOrder($order)->create([
        'method' => $method,
        'status' => $paymentStatus,
        'amount' => $sales + $tip,
        'tip_amount' => $tip,
        'refunded_amount' => $refund,
        'paid_at' => Carbon::parse($day.' 20:00'),
    ]);

    return $order;
}

function openReport(User $user, array $query = [])
{
    return test()->actingAs($user)->get('/app/reports?'.http_build_query($query));
}

it('totals equal the sum of the rows for the selected range', function () {
    sale($this->restaurant, $this->nadia, '2026-10-20', 4000, tip: 400);
    sale($this->restaurant, $this->nadia, '2026-10-20', 2500, tip: 0, method: 'cash');
    sale($this->restaurant, $this->nadia, '2026-10-22', 6000, tip: 600, party: 4);
    sale($this->restaurant, $this->nadia, '2026-09-30', 9999); // outside "this month"

    openReport($this->sara, ['preset' => 'month'])
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $rows = collect($props['rows']);

            expect($rows)->toHaveCount(2)
                ->and($rows->sum('net_minor'))->toBe($props['totals']['net_minor'])
                ->and($rows->sum('tips_minor'))->toBe($props['totals']['tips_minor'])
                ->and($rows->sum('orders'))->toBe(3)
                ->and($props['totals']['net_minor'])->toBe(12500)
                ->and($props['totals']['tips_minor'])->toBe(1000)
                ->and($rows->firstWhere('date', '2026-10-20')['method'])->toBe('card')
                ->and($rows->firstWhere('date', '2026-10-22')['covers'])->toBe(4);

            return $page;
        });
});

it('subtracts refunds from net and shows them as a separate total', function () {
    sale($this->restaurant, $this->nadia, '2026-10-21', 5000, refund: 1200);

    openReport($this->sara, ['preset' => 'month'])
        ->assertInertia(fn ($page) => $page
            ->where('totals.gross_minor', 5000)
            ->where('totals.refunds_minor', 1200)
            ->where('totals.net_minor', 3800)
            ->where('rows.0.net_minor', 3800));
});

it('leaves out voided orders and payments that never settled', function () {
    sale($this->restaurant, $this->nadia, '2026-10-21', 3000);
    sale($this->restaurant, $this->nadia, '2026-10-21', 7000, orderStatus: 'cancelled');
    sale($this->restaurant, $this->nadia, '2026-10-21', 8000, paymentStatus: 'failed');
    sale($this->restaurant, $this->nadia, '2026-10-21', 9000, paymentStatus: 'pending');

    openReport($this->sara, ['preset' => 'month'])
        ->assertInertia(fn ($page) => $page
            ->where('totals.net_minor', 3000)
            ->where('totals.orders', 1));
});

it('flags the peak day in the series', function () {
    sale($this->restaurant, $this->nadia, '2026-10-19', 2000);
    sale($this->restaurant, $this->nadia, '2026-10-24', 9000);

    openReport($this->sara, ['preset' => 'week'])
        ->assertInertia(function ($page) {
            $series = collect($page->toArray()['props']['series']);

            expect($series)->toHaveCount(6) // Mon 19 – Sat 24
                ->and($series->where('peak', true)->pluck('date')->all())->toBe(['2026-10-24']);

            return $page;
        });
});

it('rejects a custom range that ends before it starts', function () {
    openReport($this->sara, ['preset' => 'custom', 'start' => '2026-10-20', 'end' => '2026-10-10'])
        ->assertSessionHasErrors('end');
});

it('accepts a custom range', function () {
    sale($this->restaurant, $this->nadia, '2026-10-05', 1500);

    openReport($this->sara, ['preset' => 'custom', 'start' => '2026-10-01', 'end' => '2026-10-10'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.start', '2026-10-01')
            ->where('range.end', '2026-10-10')
            ->where('totals.net_minor', 1500));
});

it('keeps waiters out and lets managers and admins in', function () {
    openReport($this->nadia)->assertForbidden();
    test()->actingAs($this->nadia)->get('/app/reports/export.csv')->assertForbidden();
    test()->actingAs($this->nadia)->get('/app/reports/export.pdf')->assertForbidden();

    openReport($this->sara)->assertOk();

    $admin = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $admin->assignRole('admin');

    openReport($admin)->assertOk();
});

it('exports a CSV with a header row that matches the rendered rows exactly', function () {
    sale($this->restaurant, $this->nadia, '2026-10-20', 4000, tip: 400);
    sale($this->restaurant, $this->nadia, '2026-10-22', 6050, tip: 605, method: 'wallet');

    $rows = openReport($this->sara, ['preset' => 'month'])->viewData('page')['props']['rows'];

    $csv = test()->actingAs($this->sara)
        ->get('/app/reports/export.csv?preset=month')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

    expect($lines[0])->toBe(['Date', 'Waiter', 'Covers', 'Orders', 'Net', 'Tips', 'Method']);

    foreach ($rows as $i => $row) {
        expect($lines[$i + 1])->toBe([
            $row['date'], $row['waiter'], (string) $row['covers'], (string) $row['orders'],
            number_format($row['net_minor'] / 100, 2, '.', ''),
            number_format($row['tips_minor'] / 100, 2, '.', ''),
            SalesReport::methodLabel($row['method']),
        ]);
    }

    expect(end($lines)[0])->toBe('Total')
        ->and(end($lines)[4])->toBe('100.50');

    $export = ReportExport::query()->latest('id')->firstOrFail();

    expect($export->format)->toBe('csv')
        ->and($export->user_id)->toBe($this->sara->id)
        ->and($export->generated_at)->not->toBeNull()
        ->and($export->row_count)->toBe(count($rows));
});

it('renders the PDF from the print view', function () {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('PDF export needs dompdf: composer require dompdf/dompdf');
    }

    sale($this->restaurant, $this->nadia, '2026-10-20', 4000, tip: 400);

    $response = test()->actingAs($this->sara)
        ->get('/app/reports/export.pdf?preset=month')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-')
        ->and(ReportExport::query()->where('format', 'pdf')->where('user_id', $this->sara->id)->exists())->toBeTrue();
});

it('gives the demo a Saturday peak over three weeks, and the total row matches the cards', function () {
    Carbon::setTestNow();
    $this->seed(AlBustanSeeder::class);

    $sara = User::role('manager')->whereHas('restaurant', fn ($q) => $q->where('slug', 'al-bustan'))->firstOrFail();

    openReport($sara, ['preset' => '3weeks'])
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $peak = collect($props['series'])->firstWhere('peak', true);

            expect(Carbon::parse($peak['date'])->isSaturday())->toBeTrue()
                ->and(collect($props['rows'])->sum('net_minor'))->toBe($props['totals']['net_minor'])
                ->and($props['totals']['gross_minor'] - $props['totals']['refunds_minor'])->toBe($props['totals']['net_minor'])
                ->and($props['totals']['refunds_minor'])->toBeGreaterThan(0);

            return $page;
        });
});
