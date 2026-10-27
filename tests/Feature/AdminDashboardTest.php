<?php

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Admin\AdminDashboardSummary;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    Carbon::setTestNow(Carbon::parse('2026-10-20 14:00:00', 'UTC'));

    $this->restaurant = Restaurant::factory()->create(['timezone' => 'UTC']);
});

afterEach(fn () => Carbon::setTestNow());

function settled(Restaurant $restaurant, string $method, int $amount, string $paidAt, int $refund = 0, ?string $refundedAt = null): Payment
{
    $order = Order::factory()->for($restaurant)->create();

    return Payment::factory()->forOrder($order)->create([
        'method' => $method,
        'status' => $refund > 0 ? 'refunded' : 'paid',
        'amount' => $amount,
        'paid_at' => Carbon::parse($paidAt),
        'refunded_amount' => $refund,
        'refunded_at' => $refundedAt ? Carbon::parse($refundedAt) : null,
    ]);
}

it('revenue month to date equals settled payments minus refunds for the current month', function () {
    config(['qresto.payments.gateway_fee_pct' => 0]);

    settled($this->restaurant, 'card', 10000, '2026-10-02 19:00');
    settled($this->restaurant, 'cash', 5000, '2026-10-10 20:00');
    settled($this->restaurant, 'pos', 3000, '2026-10-15 21:00', refund: 1000, refundedAt: '2026-10-16 10:00');

    // Last month: neither the payment nor its refund count.
    settled($this->restaurant, 'card', 99999, '2026-09-28 19:00', refund: 500, refundedAt: '2026-09-29 10:00');

    // A pending and a failed payment never count.
    Payment::factory()->forOrder(Order::factory()->for($this->restaurant)->create())->pending()->create(['amount' => 7777]);
    Payment::factory()->forOrder(Order::factory()->for($this->restaurant)->create())->failed()->create(['amount' => 8888]);

    // Another restaurant's money is not ours.
    settled(Restaurant::factory()->create(), 'card', 50000, '2026-10-05 19:00');

    $summary = app(AdminDashboardSummary::class)->for($this->restaurant);

    expect($summary['revenue']['settled_minor'])->toBe(18000)
        ->and($summary['revenue']['refunds_minor'])->toBe(1000)
        ->and($summary['revenue']['mtd_minor'])->toBe(17000)
        ->and($summary['revenue']['net_of_fees_minor'])->toBe(17000);
});

it('nets estimated gateway fees off in-app payments only', function () {
    config(['qresto.payments.gateway_fee_pct' => 3]);

    settled($this->restaurant, 'card', 10000, '2026-10-02 19:00');
    settled($this->restaurant, 'cash', 10000, '2026-10-03 19:00');

    $revenue = app(AdminDashboardSummary::class)->for($this->restaurant)['revenue'];

    expect($revenue['fees_minor'])->toBe(300)
        ->and($revenue['net_of_fees_minor'])->toBe(19700);
});

it('splits payments into app, cash and pos with the app share', function () {
    settled($this->restaurant, 'card', 6000, '2026-10-02 19:00');
    settled($this->restaurant, 'wallet', 1100, '2026-10-02 19:30');
    settled($this->restaurant, 'cash', 2000, '2026-10-03 19:00');
    settled($this->restaurant, 'pos', 900, '2026-10-03 19:30');

    $split = app(AdminDashboardSummary::class)->for($this->restaurant)['payment_split'];

    expect($split)->toBe([
        'app_minor' => 7100,
        'cash_minor' => 2000,
        'pos_minor' => 900,
        'app_share' => 71,
    ]);
});

it('reports menu health and failed-payment recovery', function () {
    MenuItem::factory()->for($this->restaurant)->count(3)->create(['is_available' => true, 'is_scheduled' => false]);
    MenuItem::factory()->for($this->restaurant)->count(2)->create(['is_available' => false, 'is_scheduled' => false]);
    MenuItem::factory()->for($this->restaurant)->create(['is_available' => true, 'is_scheduled' => true]);

    $recovered = Order::factory()->for($this->restaurant)->create(['is_paid' => true]);
    $open = Order::factory()->for($this->restaurant)->create(['is_paid' => false]);

    Payment::factory()->forOrder($recovered)->failed()->create();
    Payment::factory()->forOrder($open)->failed()->create();

    $summary = app(AdminDashboardSummary::class)->for($this->restaurant);

    expect($summary['menu'])->toBe(['live_count' => 3, 'sold_out' => 2, 'scheduled' => 1])
        ->and($summary['failed_payments'])->toBe(['count' => 2, 'recovered' => 1]);
});

it('renders the dashboard for the admin with restaurant details and health', function () {
    $admin = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/app/system')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/admin/system')
            ->has('summary.revenue.mtd_minor')
            ->where('restaurant.name', $this->restaurant->name)
            ->has('health', 3));
});

it('a manager cannot reach the admin dashboard', function () {
    $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get('/app/system')->assertForbidden();
});

it('subtitles the dashboard with the restaurant, its area and the owner account', function () {
    $this->restaurant->update(['name' => 'Al Bustan', 'address' => '14 Marsh Lane, Downtown']);

    $admin = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/app/system')
        ->assertInertia(fn ($page) => $page
            ->where('staffLayout.pageSubtitle', 'Al Bustan · Downtown · owner account'));
});
