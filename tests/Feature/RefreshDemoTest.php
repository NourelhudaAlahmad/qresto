<?php

use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);
});

it('brings a day-old demo back to "just seeded" without deleting anything', function () {
    $orders = Order::query()->count();
    $codes = Order::query()->orderBy('code')->pluck('code')->all();

    $this->travel(1)->days();

    // A day later: tickets are hours late and Nadia's shift has ended.
    $newestAge = now()->diffInMinutes(Order::query()->latest('placed_at')->first()->placed_at, true);
    expect($newestAge)->toBeGreaterThan(60 * 23);

    $nadia = User::query()->where('name', 'Nadia Rahman')->firstOrFail();
    expect(Shift::query()->where('user_id', $nadia->id)->where('ends_at', '>=', now())->exists())->toBeFalse();

    $this->artisan('qresto:demo-refresh')->assertSuccessful();

    $newest = Order::query()->latest('placed_at')->first();

    expect(Order::query()->count())->toBe($orders)
        ->and(Order::query()->orderBy('code')->pluck('code')->all())->toBe($codes)
        ->and((int) now()->diffInMinutes($newest->placed_at, true))->toBeLessThanOrEqual(3)
        ->and(substr((string) $newest->service_date, 0, 10))->toBe(now()->timezone($newest->restaurant->timezone)->toDateString())
        ->and(Shift::query()->where('user_id', $nadia->id)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->exists())->toBeTrue();
});

it('keeps the gaps between orders, so the board reads the same as after seeding', function () {
    $ages = fn () => Order::query()
        ->orderBy('code')
        ->get()
        ->map(fn (Order $order) => (int) now()->diffInMinutes($order->placed_at, true))
        ->all();

    $before = $ages();

    $this->travel(3)->days();
    $this->artisan('qresto:demo-refresh')->assertSuccessful();

    foreach ($ages() as $i => $age) {
        expect(abs($age - $before[$i]))->toBeLessThanOrEqual(1);
    }
});

it('says so when the demo is already current', function () {
    $this->artisan('qresto:demo-refresh')
        ->expectsOutputToContain('already current')
        ->assertSuccessful();
});

it('fails clearly when the demo restaurant is missing', function () {
    $this->artisan('qresto:demo-refresh', ['--restaurant' => 'nowhere'])
        ->expectsOutputToContain('No restaurant with that slug')
        ->assertFailed();
});
