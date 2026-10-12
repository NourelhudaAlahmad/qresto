<?php

use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\StaffAccountLocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('locks a staff account after three failed passwords', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
    ]);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors('email');
    }

    $user->refresh();

    expect($user->failed_attempts)->toBe(3)
        ->and($user->locked_until)->not->toBeNull()
        ->and($user->locked_until->isFuture())->toBeTrue();
});

it('rejects the correct password while the account is locked', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
        'failed_attempts' => 3,
        'locked_until' => now()->addMinutes(10),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'CorrectPassword123!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('allows signing in after the ten minute lock expires', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
        'failed_attempts' => 3,
        'locked_until' => now()->addMinutes(10),
    ]);

    $this->travel(11)->minutes();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'CorrectPassword123!',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    expect($user->failed_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull()
        ->and($user->last_login_at)->not->toBeNull();
});

it('notifies only the on-shift manager when a staff account is locked', function () {
    Notification::fake();

    // Create the manager role in the test database.
    Role::findOrCreate('manager', 'web');

    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $manager->assignRole('manager');

    $otherManager = User::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'is_active' => true,
    ]);

    $otherManager->assignRole('manager');

    Shift::factory()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $manager->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(4),
    ]);

    Shift::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'user_id' => $otherManager->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(4),
    ]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors('email');
    }

    $staff->refresh();

    expect($staff->failed_attempts)->toBe(3)
        ->and($staff->locked_until)->not->toBeNull();

    Notification::assertSentTo(
        $manager,
        StaffAccountLocked::class,
        fn (StaffAccountLocked $notification) => $notification->lockedUser->id === $staff->id,
    );

    Notification::assertNotSentTo(
        $otherManager,
        StaffAccountLocked::class,
    );

    Notification::assertCount(1);
});
