<?php

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\PermissionAudit;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Admin\PermissionMatrix;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();

    $this->yusuf = matrixUser($this->restaurant, 'admin', 'Yusuf Aziz');
});

function matrixUser(Restaurant $restaurant, string $role, string $name = 'Staff'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
        'password' => 'password',
    ]);

    $user->assignRole($role);

    return $user;
}

function roleHas(string $role, Capability $capability): bool
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return SpatieRole::findByName($role, 'web')->hasPermissionTo($capability->value);
}

function toggleCell(string $role, Capability $capability, bool $granted): array
{
    return [
        'role' => $role,
        'capability' => $capability->value,
        'granted' => $granted,
    ];
}

it('shows the seeded matrix exactly as the design draws it', function () {
    // The design's MATRIX: columns Waiter, Kitchen, Manager, Admin.
    $design = [
        'See own tables' => [true, false, true, true],
        'See all orders' => [false, true, true, true],
        'Change order status' => [true, true, true, true],
        'Take payment · refund' => [true, false, true, true],
        'Manage waiter accounts' => [false, false, true, true],
        'Edit menu & prices' => [false, false, false, true],
        'Financial reports · export' => [false, false, true, true],
        'Restaurant & system config' => [false, false, false, true],
    ];

    $this->actingAs($this->yusuf)
        ->get('/app/system')
        ->assertOk()
        ->assertInertia(function ($page) use ($design) {
            $page->component('staff/admin/system')
                ->where('matrix.roles', fn ($roles) => collect($roles)->pluck('key')->all() === [
                    'waiter', 'runner', 'cashier', 'kitchen', 'manager', 'admin',
                ]);

            $rows = $page->toArray()['props']['matrix']['rows'];

            expect(array_column($rows, 'label'))->toBe(array_keys($design));

            foreach ($rows as $row) {
                $cells = $row['cells'];

                expect([
                    $cells['waiter']['granted'],
                    $cells['kitchen']['granted'],
                    $cells['manager']['granted'],
                    $cells['admin']['granted'],
                ])->toBe($design[$row['label']])
                    ->and($cells['runner']['granted'])->toBeFalse()
                    ->and($cells['cashier']['granted'])->toBeFalse();
            }

            return $page;
        });
});

it('toggling a capability updates the role and writes exactly one audit row', function () {
    $this->actingAs($this->yusuf)
        ->from('/app/system')
        ->patch('/app/system/permissions', toggleCell('manager', Capability::EDIT_MENU_PRICES, true))
        ->assertRedirect('/app/system')
        ->assertSessionHasNoErrors();

    expect(roleHas('manager', Capability::EDIT_MENU_PRICES))->toBeTrue()
        ->and(PermissionAudit::count())->toBe(1);

    $audit = PermissionAudit::first();

    expect($audit->actor_id)->toBe($this->yusuf->id)
        ->and($audit->role)->toBe('manager')
        ->and($audit->permission)->toBe('edit_menu_prices')
        ->and($audit->granted)->toBeTrue()
        ->and($audit->created_at)->not->toBeNull();

    // A repeated click is a no-op, not a second audit row.
    $this->actingAs($this->yusuf)
        ->patch('/app/system/permissions', toggleCell('manager', Capability::EDIT_MENU_PRICES, true));

    expect(PermissionAudit::count())->toBe(1);

    $this->actingAs($this->yusuf)
        ->patch('/app/system/permissions', toggleCell('manager', Capability::EDIT_MENU_PRICES, false));

    expect(roleHas('manager', Capability::EDIT_MENU_PRICES))->toBeFalse()
        ->and(PermissionAudit::count())->toBe(2)
        ->and(PermissionAudit::latest('id')->first()->granted)->toBeFalse();
});

it('keeps the old sidebar for an active session and shows the new one after sign-in', function () {
    $sara = matrixUser($this->restaurant, 'manager', 'Sara Malik');

    // Sara signs in for her shift.
    $this->post(route('login.store'), ['email' => $sara->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($sara);

    $navIds = fn () => collect(
        $this->get('/manager/overview')->viewData('page')['props']['nav'],
    )->pluck('id')->all();

    expect($navIds())->not->toContain('menu');

    // Mid-shift, Yusuf grants her role Edit menu & prices.
    app(PermissionMatrix::class)->set(
        $this->yusuf,
        Role::MANAGER,
        Capability::EDIT_MENU_PRICES,
        true,
    );

    // Same session: unchanged sidebar.
    expect($navIds())->not->toContain('menu');

    // Next sign-in: the Menu item is there.
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $sara->email, 'password' => 'password']);

    expect($navIds())->toContain('menu');
});

it('never lets the admin role lose system config', function () {
    $other = matrixUser($this->restaurant, 'manager');
    $other->givePermissionTo(Capability::MANAGE_RESTAURANT_CONFIG->value);

    $this->actingAs($other)
        ->patch('/app/system/permissions', toggleCell('admin', Capability::MANAGE_RESTAURANT_CONFIG, false))
        ->assertSessionHasErrors('capability');

    expect(roleHas('admin', Capability::MANAGE_RESTAURANT_CONFIG))->toBeTrue()
        ->and(PermissionAudit::count())->toBe(0);
});

it('does not let anyone revoke a capability from their own role', function () {
    $this->actingAs($this->yusuf)
        ->patch('/app/system/permissions', toggleCell('admin', Capability::FINANCIAL_REPORTS, false))
        ->assertSessionHasErrors('capability');

    expect(roleHas('admin', Capability::FINANCIAL_REPORTS))->toBeTrue();
});

it('marks those locked cells in the matrix with the reason', function () {
    $this->actingAs($this->yusuf)
        ->get('/app/system')
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['matrix']['rows'])->keyBy('capability');

            expect($rows['manage_restaurant_config']['cells']['admin']['locked'])->toBeTrue()
                ->and($rows['manage_restaurant_config']['cells']['admin']['reason'])->toContain('system config')
                ->and($rows['financial_reports']['cells']['admin']['locked'])->toBeTrue()
                ->and($rows['financial_reports']['cells']['manager']['locked'])->toBeFalse();

            return $page;
        });
});

it('rejects unknown roles and capabilities', function () {
    $this->actingAs($this->yusuf)
        ->patch('/app/system/permissions', ['role' => 'owner', 'capability' => 'launch_rockets', 'granted' => true])
        ->assertSessionHasErrors(['role', 'capability']);
});

it('keeps managers, waiters and kitchen off the screen and the endpoints', function (string $role) {
    $user = matrixUser($this->restaurant, $role);

    $this->actingAs($user)->get('/app/system')->assertForbidden();
    $this->actingAs($user)->get('/app/system/permissions')->assertForbidden();
    $this->actingAs($user)->get('/app/system/permissions/audits')->assertForbidden();
    $this->actingAs($user)
        ->patch('/app/system/permissions', toggleCell('manager', Capability::EDIT_MENU_PRICES, true))
        ->assertForbidden();

    expect(roleHas('manager', Capability::EDIT_MENU_PRICES))->toBeFalse();
})->with(['manager', 'waiter', 'kitchen']);

it('lists recent changes with the actor and time', function () {
    $this->actingAs($this->yusuf)
        ->patch('/app/system/permissions', toggleCell('manager', Capability::EDIT_MENU_PRICES, true));

    $this->actingAs($this->yusuf)
        ->getJson('/app/system/permissions/audits')
        ->assertOk()
        ->assertJsonPath('audits.0.actor', 'Yusuf Aziz')
        ->assertJsonPath('audits.0.role', 'Manager')
        ->assertJsonPath('audits.0.capability', 'Edit menu & prices')
        ->assertJsonPath('audits.0.granted', true)
        ->assertJsonStructure(['audits' => [['id', 'actor', 'role', 'capability', 'granted', 'at']]]);

    $this->actingAs($this->yusuf)
        ->get('/app/system')
        ->assertInertia(fn ($page) => $page->where('audits.0.actor', 'Yusuf Aziz'));
});

it('returns the matrix as JSON for the index endpoint', function () {
    $this->actingAs($this->yusuf)
        ->getJson('/app/system/permissions')
        ->assertOk()
        ->assertJsonCount(6, 'roles')
        ->assertJsonCount(8, 'rows');
});
