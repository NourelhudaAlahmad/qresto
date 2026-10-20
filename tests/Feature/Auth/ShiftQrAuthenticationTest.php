<?php

use App\Enums\Capability;
use App\Models\Restaurant;
use App\Models\ShiftSignInToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createShiftQrToken(
    User $staff,
    ?string $rawToken = null,
): string {
    $rawToken ??= Str::random(64);

    ShiftSignInToken::query()->create([
        'restaurant_id' => $staff->restaurant_id,
        'user_id' => $staff->id,
        'token' => hash('sha256', $rawToken),
        'issued_by' => $staff->id,
        'expires_at' => now()->addMinutes(5),
    ]);

    return $rawToken;
}

it('signs in an active staff member with a valid shift QR', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertRedirect();

    $this->assertAuthenticatedAs($staff);

    $this->assertDatabaseHas('shift_sign_in_tokens', [
        'token' => hash('sha256', $token),
        'used_by' => $staff->id,
    ]);

    expect(
        ShiftSignInToken::query()
            ->where('token', hash('sha256', $token))
            ->firstOrFail()
            ->used_at,
    )->not->toBeNull();
});

it('rejects a shift QR after it has been used once', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertSessionHasNoErrors();

    $this->post('/logout');

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('rejects an expired shift QR', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    $this->travel(6)->minutes();

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('rejects a shift QR for a different restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $otherRestaurant->slug,
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('rejects a shift QR for an inactive staff member', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => false,
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('rejects a shift QR for a locked staff account', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'failed_attempts' => 3,
        'locked_until' => now()->addMinutes(10),
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('allows an authorized manager to issue a shift QR for staff in the same restaurant', function () {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    Role::findOrCreate('manager', 'web');

    Permission::findOrCreate(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
        'web',
    );

    $manager->assignRole('manager');
    $manager->givePermissionTo(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
    );

    $this->actingAs($manager)
        ->post(route('auth.shift-qr.issue'), [
            'user_id' => $staff->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('shift_qr_token');

    $this->assertDatabaseHas('shift_sign_in_tokens', [
        'restaurant_id' => $restaurant->id,
        'user_id' => $staff->id,
        'issued_by' => $manager->id,
    ]);
});

it('rejects shift QR issuance by a user without manager authorization', function () {
    $restaurant = Restaurant::factory()->create();

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('auth.shift-qr.issue'), [
            'user_id' => $staff->id,
        ])
        ->assertSessionHasErrors('staff');

    $this->assertDatabaseCount('shift_sign_in_tokens', 0);
});

it('prevents a manager from issuing a shift QR for another restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'is_active' => true,
    ]);

    Role::findOrCreate('manager', 'web');

    Permission::findOrCreate(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
        'web',
    );

    $manager->assignRole('manager');
    $manager->givePermissionTo(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
    );

    $this->actingAs($manager)
        ->post(route('auth.shift-qr.issue'), [
            'user_id' => $staff->id,
        ])
        ->assertSessionHasErrors('staff');

    $this->assertDatabaseCount('shift_sign_in_tokens', 0);
});

it('requires two-factor authentication when signing in with a shift QR', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->withTwoFactor()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $staff->id)
        ->assertSessionHas('login.remember', false);

    // The QR must not authenticate staff before 2FA is completed.
    $this->assertGuest();

    // The QR is consumed even when a 2FA challenge is required.
    $this->assertDatabaseHas('shift_sign_in_tokens', [
        'token' => hash('sha256', $token),
        'used_by' => $staff->id,
    ]);
});

it('completes two-factor authentication after shift QR sign-in', function () {
    $restaurant = Restaurant::factory()->create();

    $staff = User::factory()->withTwoFactor()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $token = createShiftQrToken($staff);

    // Step 1: Start authentication using the shift QR.
    $this->post(route('auth.shift-qr.store'), [
        'token' => $token,
        'restaurant_slug' => $restaurant->slug,
    ])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $staff->id);

    // The staff member must not be signed in yet.
    $this->assertGuest();

    // Step 2: Complete Fortify's challenge with a recovery code.
    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertSessionHasNoErrors();

    // Authentication must only succeed after the second factor.
    $this->assertAuthenticatedAs($staff);

    // The pending challenge should be removed.
    $this->assertFalse(session()->has('login.id'));
});

it('returns the generated shift QR to the authorized manager page', function () {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    Role::findOrCreate('manager', 'web');

    Permission::findOrCreate(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
        'web',
    );

    $manager->assignRole('manager');
    $manager->givePermissionTo(
        Capability::MANAGE_WAITER_ACCOUNTS->value,
    );

    $this->actingAs($manager)
        ->post(route('auth.shift-qr.issue'), [
            'user_id' => $staff->id,
        ])
        ->assertRedirect(route('manager.shift-qr.index'))
        ->assertSessionHas('shift_qr_token');

    $this->get(route('manager.shift-qr.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('manager/shift-qr')
                ->where('restaurantSlug', $restaurant->slug)
                ->has('staff', 1)
                ->where('staff.0.id', $staff->id)
                ->where(
                    'shiftQrToken',
                    fn ($token) => is_string($token) && strlen($token) === 64
                ),
        );
});
