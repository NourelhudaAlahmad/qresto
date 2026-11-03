<?php

use App\Models\User;
use App\Services\Auth\StaffAccountLockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/*
 * #51: "Keep me signed in on this device" sets Laravel's remember cookie and
 * gives the session a shift-long lifetime; unticked keeps the stock lifetime.
 * A lockout invalidates any remember cookie issued before it.
 */

function rememberCookieName(): string
{
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');

    return $guard->getRecallerName();
}

function rememberStaffMember(array $attributes = []): User
{
    return User::factory()->create(['is_active' => true] + $attributes);
}

/** Forget who is signed in, as a browser does once its session is gone. */
function rememberDropSession(): void
{
    Auth::forgetGuards();
    session()->flush();
}

function rememberSignIn(User $user, bool $remember): TestResponse
{
    return test()->post(route('login.store'), array_filter([
        'email' => $user->email,
        'password' => 'password',
        'remember' => $remember ? '1' : null,
    ]));
}

it('reads the remember cookie by the name the guard gives it', function () {
    expect(rememberCookieName())->toBe('remember_web_'.sha1(SessionGuard::class));
});

it('sets the remember cookie only when the box is ticked', function (bool $remember) {
    $response = rememberSignIn(rememberStaffMember(), $remember);

    $remember
        ? $response->assertCookie(rememberCookieName())
        : $response->assertCookieMissing(rememberCookieName());
})->with(['ticked' => true, 'unticked' => false]);

it('gives a remembered session a shift-long cookie and leaves the unticked one on the stock lifetime', function (bool $remember) {
    config(['session.lifetime' => 120, 'qresto.auth.remembered_session_minutes' => 780]);

    $expires = rememberSignIn(rememberStaffMember(), $remember)
        ->getCookie(config('session.cookie'), decrypt: false)
        ->getExpiresTime();

    $minutes = (int) round(($expires - now()->getTimestamp()) / 60);

    expect($minutes)->toBe($remember ? 780 : 120);
})->with(['ticked' => true, 'unticked' => false]);

it('keeps a remembered member signed in past the stock lifetime, and an unticked one not', function (bool $remember) {
    $user = rememberStaffMember();
    $response = rememberSignIn($user, $remember);

    $cookie = $remember ? $response->getCookie(rememberCookieName())->getValue() : null;

    $this->travel((int) config('session.lifetime') + 60)->minutes();
    rememberDropSession();

    $request = $cookie === null ? $this : $this->withCookie(rememberCookieName(), $cookie);
    $request->get(route('profile.edit'));

    $remember ? $this->assertAuthenticatedAs($user) : $this->assertGuest();
})->with(['ticked' => true, 'unticked' => false]);

it('keeps remember through the two-factor challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['is_active' => true]);

    rememberSignIn($user, true)
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.remember', true);

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])
        ->assertSessionHasNoErrors()
        ->assertCookie(rememberCookieName());

    $this->assertAuthenticatedAs($user);
});

it('stops an old remember cookie from signing a locked account back in', function () {
    $user = rememberStaffMember();
    $cookie = rememberSignIn($user, true)->getCookie(rememberCookieName())->getValue();

    // Someone else gets the password wrong three times.
    foreach (range(1, 3) as $attempt) {
        app(StaffAccountLockout::class)->recordFailure($user->fresh());
    }

    rememberDropSession();

    $this->withCookie(rememberCookieName(), $cookie)->get(route('profile.edit'));

    $this->assertGuest();
});
