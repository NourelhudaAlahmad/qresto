<?php

use App\Models\Integration;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\IntegrationFailing;
use App\Notifications\KitchenOrderVoided;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();

    $this->sara = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->sara->assignRole('manager');

    $this->omar = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->omar->assignRole('manager');

    $printer = Integration::query()->create([
        'restaurant_id' => $this->restaurant->id,
        'kind' => 'printer',
        'name' => 'Receipt printer, pass',
        'detail' => 'out of paper',
    ]);

    $this->sara->notify(new IntegrationFailing($printer, 'failing'));
});

it('lists my notifications with a title, message and where to go', function () {
    $this->actingAs($this->sara)
        ->getJson('/app/notifications')
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notifications.0.title', 'System health')
        ->assertJsonPath('notifications.0.message', 'Receipt printer, pass is failing: out of paper')
        ->assertJsonPath('notifications.0.url', '/app/system')
        ->assertJsonPath('notifications.0.read', false);
});

it('never shows or touches someone else\'s notifications', function () {
    $id = $this->sara->notifications()->first()->id;

    $this->actingAs($this->omar)
        ->getJson('/app/notifications')
        ->assertJsonCount(0, 'notifications');

    $this->actingAs($this->omar)
        ->post("/app/notifications/{$id}/read")
        ->assertNotFound();

    expect($this->sara->fresh()->unreadNotifications()->count())->toBe(1);
});

it('marks one notification read, and the unread count follows', function () {
    $id = $this->sara->notifications()->first()->id;

    $this->actingAs($this->sara)
        ->post("/app/notifications/{$id}/read")
        ->assertRedirect();

    expect($this->sara->fresh()->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->sara)
        ->get('/manager/overview')
        ->assertInertia(fn ($page) => $page->where('staffUnreadNotifications', 0));
});

it('marks all read at once', function () {
    $this->sara->notify(new KitchenOrderVoided(1, '#A-1044', 'guest left'));

    expect($this->sara->unreadNotifications()->count())->toBe(2);

    $this->actingAs($this->sara)->post('/app/notifications/read-all')->assertRedirect();

    expect($this->sara->fresh()->unreadNotifications()->count())->toBe(0);
});

it('needs you to be signed in', function () {
    $this->getJson('/app/notifications')->assertUnauthorized();
});
