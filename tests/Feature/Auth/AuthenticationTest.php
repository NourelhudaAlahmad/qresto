<?php

use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('staff members are redirected to an available landing screen after login', function () {
    $roles = [
        'waiter',
        'kitchen',
        'manager',
        'admin',
    ];

    $this->seed(RolesSeeder::class);

    foreach ($roles as $role) {

        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole($role);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        // Each role's real working screen, never a placeholder.
        $destinations = [
            'waiter' => 'app.my-orders',
            'kitchen' => 'app.pass',
            'manager' => 'manager.overview',
            'admin' => 'app.system',
        ];

        $response->assertRedirect(
            route($destinations[$role], absolute: false),
        );

        $this->post(route('logout'));
    }
});

test('the old role landings redirect to the real screens', function () {
    $this->seed(RolesSeeder::class);

    $admin = User::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    $this->get('/waiter/orders')->assertRedirect('/app/my-orders');
    $this->get('/kitchen/pass')->assertRedirect('/app/pass');
    $this->get('/admin/system')->assertRedirect('/app/system');
    $this->get('/app/overview')->assertRedirect('/manager/overview');
});

test('a role whose screen was revoked falls back instead of landing on a 403', function () {
    $this->seed(RolesSeeder::class);

    Role::findByName('manager', 'web')
        ->revokePermissionTo('manage_waiter_accounts');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');

    $this->post(route('login.store'), [
        'email' => $manager->email,
        'password' => 'password',
    ])->assertRedirect(route('app.orders', absolute: false));
});

test('remember me creates a persistent login token', function () {
    $user = User::factory()->create([
        'remember_token' => null,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(
        route('dashboard', absolute: false),
    );

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->getRememberToken())
        ->not->toBeNull()
        ->not->toBe('');
});
