<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\StaffAudit;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();

    foreach (['01', '02', '04', '07', '09', '12'] as $index => $number) {
        RestaurantTable::factory()
            ->forRestaurant($this->restaurant)
            ->create(['number' => $number, 'sort_order' => $index]);
    }

    $this->manager = staffMember($this->restaurant, 'manager', 'Sara Malik');
});

function staffMember(Restaurant $restaurant, string $role, string $name = 'Staff'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

/**
 * @param  list<string>  $numbers
 */
function giveTables(User $member, array $numbers): Shift
{
    $shift = Shift::query()->create([
        'restaurant_id' => $member->restaurant_id,
        'user_id' => $member->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(4),
    ]);

    foreach (RestaurantTable::query()->whereIn('number', $numbers)->get() as $table) {
        DB::table('table_user')->insert([
            'shift_id' => $shift->id,
            'user_id' => $member->id,
            'table_id' => $table->id,
        ]);
    }

    return $shift;
}

/**
 * @return list<string>
 */
function liveTablesOf(User $member): array
{
    return DB::table('table_user')
        ->join('shifts', 'shifts.id', '=', 'table_user.shift_id')
        ->join('tables', 'tables.id', '=', 'table_user.table_id')
        ->where('table_user.user_id', $member->id)
        ->where('shifts.starts_at', '<=', now())
        ->where('shifts.ends_at', '>=', now())
        ->orderBy('tables.number')
        ->pluck('tables.number')
        ->map(fn ($number): string => (string) $number)
        ->all();
}

function newStaffPayload(array $overrides = []): array
{
    return [
        'name' => 'Omar Faruk',
        'email' => 'omar@albustan.co',
        'role' => 'waiter',
        'tables' => ['04', '12'],
        'send_invite' => false,
        ...$overrides,
    ];
}

// ── Creating ─────────────────────────────────────────────────────────

it('lets a manager add a waiter with tonight\'s shift and tables', function () {
    $this->actingAs($this->manager)
        ->from('/manager/overview')
        ->post('/app/staff', newStaffPayload())
        ->assertRedirect('/manager/overview')
        ->assertSessionHasNoErrors();

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();

    expect($omar->hasRole('waiter'))->toBeTrue()
        ->and($omar->restaurant_id)->toBe($this->restaurant->id)
        ->and($omar->is_active)->toBeTrue()
        ->and($omar->initials)->toBe('OF')
        ->and(liveTablesOf($omar))->toBe(['04', '12'])
        ->and($omar->shifts()->count())->toBe(1);
});

it('moves a table off whoever had it tonight', function () {
    $nadia = staffMember($this->restaurant, 'waiter', 'Nadia Rahman');
    giveTables($nadia, ['04', '12']);

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['tables' => ['04']]))
        ->assertSessionHasNoErrors();

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();

    expect(liveTablesOf($omar))->toBe(['04'])
        ->and(liveTablesOf($nadia))->toBe(['12']);
});

it('accepts runner, cashier and kitchen accounts', function (string $role) {
    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload([
            'email' => "{$role}@albustan.co",
            'role' => $role,
            'tables' => [],
        ]))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', "{$role}@albustan.co")->firstOrFail()->hasRole($role))
        ->toBeTrue();
})->with(['runner', 'cashier', 'kitchen']);

it('refuses to let a manager create a manager or an admin', function (string $role) {
    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['role' => $role]))
        ->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'omar@albustan.co')->exists())->toBeFalse();
})->with(['manager', 'admin']);

it('validates the email and the tables', function () {
    staffMember($this->restaurant, 'waiter')->update(['email' => 'taken@albustan.co']);

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload([
            'email' => 'taken@albustan.co',
            'tables' => ['99'],
        ]))
        ->assertSessionHasErrors(['email', 'tables.0']);
});

it('rejects a table from another restaurant', function () {
    $other = Restaurant::factory()->create();
    RestaurantTable::factory()->forRestaurant($other)->create(['number' => '77']);

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['tables' => ['77']]))
        ->assertSessionHasErrors('tables.0');
});

it('keeps waiters and kitchen accounts out of staff management', function (string $role) {
    $this->actingAs(staffMember($this->restaurant, $role))
        ->post('/app/staff', newStaffPayload())
        ->assertForbidden();
})->with(['waiter', 'kitchen']);

// ── Invitations ──────────────────────────────────────────────────────

it('queues a set-password invitation when asked', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['send_invite' => true]))
        ->assertSessionHasNoErrors();

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();

    Notification::assertSentTo($omar, StaffInvitation::class);

    expect(StaffInvitation::class)
        ->toImplement(ShouldQueue::class);
});

it('sends no invitation when the box is unticked', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['send_invite' => false]));

    Notification::assertNothingSent();
});

it('sets the password through the invitation link', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['send_invite' => true]));

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();
    $token = null;

    Notification::assertSentTo(
        $omar,
        StaffInvitation::class,
        function (StaffInvitation $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    auth()->logout();

    $this->get("/staff/invitation/{$token}?email={$omar->email}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/set-password'));

    $this->post('/staff/invitation', [
        'token' => $token,
        'email' => $omar->email,
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Correct-Horse-9', $omar->fresh()->password))->toBeTrue();
});

it('refuses an expired invitation link', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['send_invite' => true]));

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();
    $original = $omar->password;
    $token = null;

    Notification::assertSentTo($omar, StaffInvitation::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    auth()->logout();

    $this->travel((int) config('auth.passwords.staff_invites.expire') + 1)->minutes();

    $this->post('/staff/invitation', [
        'token' => $token,
        'email' => $omar->email,
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
    ])->assertSessionHasErrors('email');

    expect($omar->fresh()->password)->toBe($original);
});

it('gives invitations a longer life than forgot-password links', function () {
    expect(config('auth.passwords.staff_invites.expire'))
        ->toBeGreaterThan(config('auth.passwords.users.expire'));
});

// ── Editing ──────────────────────────────────────────────────────────

it('edits name, role and tables in place', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    giveTables($lin, ['07', '09']);

    $this->actingAs($this->manager)
        ->patch("/app/staff/{$lin->id}", [
            'name' => 'Lin W. Wu',
            'role' => 'runner',
            'tables' => ['09'],
        ])
        ->assertSessionHasNoErrors();

    $lin->refresh();

    expect($lin->name)->toBe('Lin W. Wu')
        ->and($lin->hasRole('runner'))->toBeTrue()
        ->and($lin->hasRole('waiter'))->toBeFalse()
        ->and(liveTablesOf($lin))->toBe(['09']);
});

it('cannot promote someone to manager through an edit', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');

    $this->actingAs($this->manager)
        ->patch("/app/staff/{$lin->id}", [
            'name' => 'Lin Wu',
            'role' => 'manager',
            'tables' => [],
        ])
        ->assertSessionHasErrors('role');

    expect($lin->fresh()->hasRole('manager'))->toBeFalse();
});

it('cannot change a manager, an admin or yourself', function () {
    $otherManager = staffMember($this->restaurant, 'manager');
    $admin = staffMember($this->restaurant, 'admin');

    foreach ([$otherManager, $admin, $this->manager] as $target) {
        $this->actingAs($this->manager)
            ->post("/app/staff/{$target->id}/deactivate")
            ->assertForbidden();

        expect($target->fresh()->is_active)->toBeTrue();
    }
});

it('hides another restaurant\'s staff', function () {
    $stranger = staffMember(Restaurant::factory()->create(), 'waiter');

    $this->actingAs($this->manager)
        ->post("/app/staff/{$stranger->id}/deactivate")
        ->assertNotFound();
});

// ── Deactivating ─────────────────────────────────────────────────────

it('keeps every order and releases the tables when deactivating', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    $shift = giveTables($lin, ['07', '09']);

    $history = Order::factory()
        ->for($this->restaurant)
        ->paid()
        ->count(3)
        ->create(['assigned_user_id' => $lin->id]);

    $this->actingAs($this->manager)
        ->post("/app/staff/{$lin->id}/deactivate")
        ->assertSessionHasNoErrors();

    $lin->refresh();

    expect($lin->is_active)->toBeFalse()
        ->and($lin->deactivated_at)->not->toBeNull()
        ->and($lin->deactivated_by)->toBe($this->manager->id)
        ->and(User::query()->whereKey($lin->id)->exists())->toBeTrue()
        ->and(Order::query()->where('assigned_user_id', $lin->id)->count())->toBe(3)
        ->and(liveTablesOf($lin))->toBe([])
        ->and($shift->fresh()->ends_at->lte(now()))->toBeTrue();

    expect($history->pluck('id')->all())
        ->toEqual(Order::query()->where('assigned_user_id', $lin->id)->pluck('id')->all());
});

it('refuses to deactivate someone with open orders until they are reassigned', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    $nadia = staffMember($this->restaurant, 'waiter', 'Nadia Rahman');

    $open = Order::factory()
        ->for($this->restaurant)
        ->preparing()
        ->create(['assigned_user_id' => $lin->id]);

    $this->actingAs($this->manager)
        ->post("/app/staff/{$lin->id}/deactivate")
        ->assertSessionHasErrors('member');

    expect($lin->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->manager)
        ->patch("/app/orders/{$open->id}/assign", ['assigned_user_id' => $nadia->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->post("/app/staff/{$lin->id}/deactivate")
        ->assertSessionHasNoErrors();

    expect($lin->fresh()->is_active)->toBeFalse();
});

it('blocks sign-in after deactivation and restores it on reactivation', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    $lin->forceFill(['password' => 'password'])->save();

    $this->actingAs($this->manager)->post("/app/staff/{$lin->id}/deactivate");

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $lin->email,
        'password' => 'password',
    ]);

    $this->assertGuest();

    $this->actingAs($this->manager)->post("/app/staff/{$lin->id}/reactivate");

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $lin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($lin->fresh());
});

it('ends a session that was open when the account was deactivated', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');

    $lin->forceFill([
        'is_active' => false,
        'deactivated_at' => now(),
    ])->save();

    $this->actingAs($lin->fresh())
        ->get('/app/my-orders')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

// ── Audit ────────────────────────────────────────────────────────────

it('audits every staff change with the acting manager', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload(['send_invite' => true]));

    $omar = User::query()->where('email', 'omar@albustan.co')->firstOrFail();

    $this->actingAs($this->manager)
        ->patch("/app/staff/{$omar->id}", [
            'name' => 'Omar Faruk',
            'role' => 'runner',
            'tables' => ['02'],
        ]);

    $this->actingAs($this->manager)->post("/app/staff/{$omar->id}/deactivate");
    $this->actingAs($this->manager)->post("/app/staff/{$omar->id}/reactivate");

    $audits = StaffAudit::query()->where('user_id', $omar->id)->orderBy('id')->get();

    expect($audits->pluck('action')->all())->toBe([
        StaffAudit::CREATED,
        StaffAudit::INVITED,
        StaffAudit::UPDATED,
        StaffAudit::DEACTIVATED,
        StaffAudit::REACTIVATED,
    ])
        ->and($audits->pluck('actor_id')->unique()->all())->toBe([$this->manager->id])
        ->and($audits[2]->changes['role'])->toBe(['from' => 'waiter', 'to' => 'runner'])
        ->and($audits[3]->changes['released_tables'])->toBe(['02']);
});

// ── Overview ─────────────────────────────────────────────────────────

it('lists the whole manageable team on the overview, deactivated included', function () {
    $nadia = staffMember($this->restaurant, 'waiter', 'Nadia Rahman');
    giveTables($nadia, ['04', '12']);
    staffMember($this->restaurant, 'kitchen', 'Kitchen Pass');
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    $lin->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    $this->actingAs($this->manager)
        ->get('/manager/overview')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/manager/overview')
            ->where('staffRoles', ['waiter', 'runner', 'cashier', 'kitchen'])
            ->has('staff', 3)
            ->where('staff.0.name', 'Kitchen Pass')
            ->where('staff.1.name', 'Nadia Rahman')
            ->where('staff.1.tables', ['04', '12'])
            ->where('staff.2.name', 'Lin Wu')
            ->where('staff.2.active', false));
});

it('opens the overview to anyone holding the capability, not just the manager role', function () {
    $admin = staffMember($this->restaurant, 'admin');

    $this->actingAs($admin)->get('/manager/overview')->assertOk();
});

it('shows the new owner of a table on the floor without a page navigation', function () {
    $nadia = staffMember($this->restaurant, 'waiter', 'Nadia Rahman');
    giveTables($nadia, ['04', '12']);

    $this->actingAs($this->manager)
        ->post('/app/staff', newStaffPayload())
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->get('/app/floor')
        ->assertOk()
        ->assertInertia(function ($page) {
            $tables = collect($page->toArray()['props']['tables'])->keyBy('number');

            expect($tables['04']['waiter']['name'])->toBe('Omar Faruk')
                ->and($tables['12']['waiter']['name'])->toBe('Omar Faruk')
                ->and($tables['01']['waiter'])->toBeNull();

            return $page;
        });
});

it('drops a deactivated person from the floor tiles', function () {
    $lin = staffMember($this->restaurant, 'waiter', 'Lin Wu');
    giveTables($lin, ['07']);

    $this->actingAs($this->manager)->post("/app/staff/{$lin->id}/deactivate");

    $this->actingAs($this->manager)
        ->get('/app/floor')
        ->assertInertia(function ($page) {
            $tables = collect($page->toArray()['props']['tables'])->keyBy('number');

            expect($tables['07']['waiter'])->toBeNull();

            return $page;
        });
});
