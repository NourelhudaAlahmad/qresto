<?php

use App\Models\Integration;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\IntegrationFailing;
use App\Services\Integrations\IntegrationMonitor;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();

    $this->kds = Integration::query()->create([
        'restaurant_id' => $this->restaurant->id,
        'kind' => 'kds',
        'name' => 'Kitchen display',
    ]);

    $this->token = $this->kds->issueToken();

    $this->sara = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'is_active' => true]);
    $this->sara->assignRole('manager');

    $this->nadia = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'is_active' => true]);
    $this->nadia->assignRole('waiter');
});

function beat(string $token, int $id, string $status = 'ok', ?string $detail = null)
{
    return test()->withToken($token)->postJson("/api/integrations/{$id}/heartbeat", array_filter([
        'status' => $status,
        'detail' => $detail,
    ]));
}

// ── Heartbeat endpoint ───────────────────────────────────────────────

it('records a heartbeat: status, detail and timestamp', function () {
    $this->travelTo(now()->startOfMinute());

    beat($this->token, $this->kds->id, 'ok', '2 screens up')
        ->assertOk()
        ->assertJsonPath('health', 'operational');

    $kds = $this->kds->fresh();

    expect($kds->status)->toBe('ok')
        ->and($kds->detail)->toBe('2 screens up')
        ->and($kds->last_heartbeat_at->equalTo(now()))->toBeTrue()
        ->and($kds->health())->toBe('operational');
});

it('rejects a bad token, a missing token and another device\'s token alike', function () {
    $printer = Integration::query()->create([
        'restaurant_id' => $this->restaurant->id,
        'kind' => 'printer',
        'name' => 'Receipt printer, pass',
    ]);
    $printerToken = $printer->issueToken();

    beat('qri_wrong', $this->kds->id)->assertUnauthorized();
    $this->postJson("/api/integrations/{$this->kds->id}/heartbeat", ['status' => 'ok'])->assertUnauthorized();
    beat($printerToken, $this->kds->id)->assertUnauthorized();
    beat($this->token, 999999)->assertUnauthorized();

    expect($this->kds->fresh()->last_heartbeat_at)->toBeNull();
});

it('validates the reported status', function () {
    beat($this->token, $this->kds->id, 'fine')->assertUnprocessable();
});

it('stops accepting the old token once a new one is issued', function () {
    $this->kds->issueToken();

    beat($this->token, $this->kds->id)->assertUnauthorized();
});

// ── Derived health ───────────────────────────────────────────────────

it('starts unknown: no row is healthy because nobody checked', function () {
    expect($this->kds->health())->toBe('unknown');
});

it('degrades a stale heartbeat to unknown without any write', function () {
    beat($this->token, $this->kds->id, 'ok')->assertOk();

    $written = $this->kds->fresh()->updated_at;

    $this->travel(config('qresto.integrations.stale_after_seconds.kds') - 10)->seconds();
    expect($this->kds->fresh()->health())->toBe('operational');

    $this->travel(20)->seconds();

    $kds = $this->kds->fresh();

    expect($kds->health())->toBe('unknown')
        ->and($kds->status)->toBe('ok')
        ->and($kds->updated_at->equalTo($written))->toBeTrue();
});

it('maps warning and failing reports', function () {
    beat($this->token, $this->kds->id, 'warning', 'one screen down');
    expect($this->kds->fresh()->health())->toBe('degraded');

    beat($this->token, $this->kds->id, 'failing', 'both screens down');
    expect($this->kds->fresh()->health())->toBe('failing');
});

// ── Alerting managers ────────────────────────────────────────────────

it('notifies managers once on healthy → failing, not on every poll', function () {
    Notification::fake();

    beat($this->token, $this->kds->id, 'ok');
    Notification::assertNothingSent();

    beat($this->token, $this->kds->id, 'failing', 'both screens down');
    beat($this->token, $this->kds->id, 'failing', 'both screens down');
    Artisan::call('qresto:check-integrations');
    Artisan::call('qresto:check-integrations');

    Notification::assertSentToTimes($this->sara, IntegrationFailing::class, 1);
    Notification::assertNotSentTo($this->nadia, IntegrationFailing::class);

    // Recovers, then fails again: that is a new transition.
    beat($this->token, $this->kds->id, 'ok');
    beat($this->token, $this->kds->id, 'failing', 'screen frozen');

    Notification::assertSentToTimes($this->sara, IntegrationFailing::class, 2);
});

it('notices a device that went silent, and says so once', function () {
    Notification::fake();

    beat($this->token, $this->kds->id, 'ok');

    $this->travel(10)->minutes();

    Artisan::call('qresto:check-integrations');
    Artisan::call('qresto:check-integrations');

    Notification::assertSentToTimes($this->sara, IntegrationFailing::class, 1);
    Notification::assertSentTo(
        $this->sara,
        IntegrationFailing::class,
        fn (IntegrationFailing $n) => $n->health === 'unknown',
    );
});

it('does not alert for a device that has never reported', function () {
    Notification::fake();

    app(IntegrationMonitor::class)->checkAll();
    app(IntegrationMonitor::class)->checkAll();

    Notification::assertNothingSent();
});

// ── Gateway probe and schedule ───────────────────────────────────────

it('probes the payment gateway and records it as a heartbeat', function () {
    $this->artisan('qresto:probe-gateway')->assertSuccessful();

    $gateway = Integration::query()
        ->where('restaurant_id', $this->restaurant->id)
        ->where('kind', 'gateway')
        ->firstOrFail();

    expect($gateway->health())->toBe('operational')
        ->and($gateway->last_heartbeat_at)->not->toBeNull();
});

it('schedules the gateway probe and the stale check', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())
        ->toContain('qresto:probe-gateway')
        ->toContain('qresto:check-integrations');
});

it('issues a device token from the command line, shown once', function () {
    $this->artisan('qresto:integration-token', ['integration' => $this->kds->id])
        ->expectsOutputToContain('It is shown once')
        ->assertSuccessful();

    beat($this->token, $this->kds->id)->assertUnauthorized();
});

// ── Admin screen ─────────────────────────────────────────────────────

it('shows derived health on the admin screen, and only to the admin', function () {
    beat($this->token, $this->kds->id, 'ok', '2 screens up');

    $admin = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/app/system')
        ->assertInertia(fn ($page) => $page
            ->where('health.0.name', 'Kitchen display')
            ->where('health.0.health', 'operational')
            ->where('health.0.detail', '2 screens up'));

    $this->travel(10)->minutes();

    $this->actingAs($admin)
        ->get('/app/system')
        ->assertInertia(fn ($page) => $page->where('health.0.health', 'unknown'));

    $this->actingAs($this->sara)->get('/app/system')->assertForbidden();
});

it('alerts when a device\'s very first report says it is failing', function () {
    Notification::fake();

    beat($this->token, $this->kds->id, 'failing', 'both screens down');

    Notification::assertSentToTimes($this->sara, IntegrationFailing::class, 1);
});

it('refuses an oversized or nested meta payload', function () {
    beat($this->token, $this->kds->id)->assertOk();

    $this->withToken($this->token)
        ->postJson("/api/integrations/{$this->kds->id}/heartbeat", [
            'status' => 'ok',
            'meta' => array_fill(0, 50, 'x'),
        ])
        ->assertUnprocessable();

    $this->withToken($this->token)
        ->postJson("/api/integrations/{$this->kds->id}/heartbeat", [
            'status' => 'ok',
            'meta' => ['paper' => str_repeat('x', 5000)],
        ])
        ->assertUnprocessable();

    $this->withToken($this->token)
        ->postJson("/api/integrations/{$this->kds->id}/heartbeat", [
            'status' => 'ok',
            'meta' => ['screens' => 2, 'paper' => 'low', 'online' => true],
        ])
        ->assertOk();
});
