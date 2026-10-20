<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('accepts a password reset token before thirty minutes', function () {
    $user = User::factory()->create();

    $token = Password::broker('users')->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertSessionHasNoErrors();

    expect(Hash::check('NewPassword123!', $user->fresh()->password))
        ->toBeTrue();
});

it('rejects a password reset token older than thirty minutes', function () {
    $user = User::factory()->create();

    $token = Password::broker('users')->createToken($user);
    $originalPassword = $user->password;

    $this->travel(31)->minutes();

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertSessionHasErrors('email');

    expect($user->fresh()->password)->toBe($originalPassword);
});
