<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\IntegrationFailing;
use App\Notifications\KitchenOrderVoided;
use App\Notifications\PaymentDisputed;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();
});

function queuedNotificationStaff(Restaurant $restaurant, string $role): User
{
    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

it('queues every notification instead of sending it inside the request', function (string $class) {
    expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue();
})->with(fn (): array => collect(glob(__DIR__.'/../../app/Notifications/*.php') ?: [])
    ->mapWithKeys(fn (string $path): array => [
        basename($path, '.php') => ['App\\Notifications\\'.basename($path, '.php')],
    ])
    ->all());

it('queues the three request-time notifications only after the transaction commits', function (string $class) {
    expect(is_subclass_of($class, ShouldQueueAfterCommit::class))->toBeTrue();
})->with([
    KitchenOrderVoided::class,
    PaymentDisputed::class,
    IntegrationFailing::class,
]);

it('does not deliver a notification sent inside a transaction until it commits', function () {
    $kitchen = queuedNotificationStaff($this->restaurant, 'kitchen');

    DB::transaction(function () use ($kitchen): void {
        $kitchen->notify(new KitchenOrderVoided(1, 'A-1044', 'guest left'));

        // The sync queue in tests would deliver at once without after-commit.
        expect($kitchen->notifications()->count())->toBe(0);
    });

    expect($kitchen->notifications()->count())->toBe(1);
});

it('drops a notification whose transaction rolls back', function () {
    $kitchen = queuedNotificationStaff($this->restaurant, 'kitchen');

    try {
        DB::transaction(function () use ($kitchen): void {
            $kitchen->notify(new KitchenOrderVoided(1, 'A-1044', 'guest left'));

            throw new RuntimeException('the void failed');
        });
    } catch (RuntimeException) {
        // Expected.
    }

    expect($kitchen->notifications()->count())->toBe(0);
});

it('voids without waiting for the pass to be notified, then notifies it from the queue', function () {
    config(['queue.default' => 'database']);

    $waiter = queuedNotificationStaff($this->restaurant, 'waiter');
    $kitchen = queuedNotificationStaff($this->restaurant, 'kitchen');

    $order = Order::factory()->assignedTo($waiter)->pending()->create();

    $this->actingAs($waiter)
        ->post("/app/orders/{$order->id}/void", ['reason' => 'Guest cancelled order'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // The request queued the fan-out and returned; no worker has run.
    expect($order->fresh()->voided_at)->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and($kitchen->notifications()->count())->toBe(0);

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($kitchen->notifications()->sole()->data['order_id'])->toBe($order->id);
});
