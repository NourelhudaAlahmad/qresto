<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('auth/two-factor-challenge'),
        );
});

test('locked staff cannot complete a pending two factor challenge', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create([
        'locked_until' => now()->addMinutes(10),
    ]);

    $response = $this
        ->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])
        ->post(route('two-factor.login.store'), [
            'code' => '000000',
        ]);

    $response->assertSessionHasErrors('code');

    $this->assertGuest();
    $this->assertFalse(session()->has('login.id'));
});
