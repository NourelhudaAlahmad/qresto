<?php

use App\Models\Restaurant;
use App\Models\ShiftSignInToken;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('staff cannot delete their own account', function () {
    $user = User::factory()->create();

    expect(Route::has('profile.destroy'))->toBeFalse();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->not->toBeNull();
});

test('a manager who issued shift QRs keeps their account and its audit trail', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
    $waiter = User::factory()->create(['restaurant_id' => $manager->restaurant_id]);

    ShiftSignInToken::query()->create([
        'restaurant_id' => $manager->restaurant_id,
        'user_id' => $waiter->id,
        'token' => hash('sha256', 'issued-by-the-manager'),
        'issued_by' => $manager->id,
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($manager)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    expect($manager->fresh())->not->toBeNull()
        ->and(ShiftSignInToken::query()->where('issued_by', $manager->id)->count())->toBe(1);
});

test('account settings sit in the staff shell with their own title', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'locale' => 'ar']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('staffLayout.pageTitle', 'الإعدادات')
            ->where('i18n.staff.shell.account_settings', 'إعدادات الحساب')
            ->where('i18n.staff.settings.security', 'الأمان'));
});
