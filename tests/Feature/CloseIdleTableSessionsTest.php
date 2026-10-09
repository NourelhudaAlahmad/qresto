
<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config()->set('qresto.table_session_idle_minutes', 30);

    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    app(CurrentRestaurant::class)->set($restaurant);

    $this->table = RestaurantTable::create([
        'number' => '01',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'idle-session-test-table',
        'sort_order' => 1,
    ]);
});

function createIdleCommandTestSession(
    RestaurantTable $table,
    string $token,
    int $idleMinutes,
): TableSession {
    return TableSession::create([
        'restaurant_table_id' => $table->id,
        'token' => $token,
        'party_size' => 1,
        'opened_at' => now()->subMinutes(60),
        'last_seen_at' => now()->subMinutes($idleMinutes),
    ]);
}

it('closes table sessions idle longer than the configured limit', function (): void {
    $session = createIdleCommandTestSession(
        $this->table,
        'idle-command-expired',
        35,
    );

    expect($session->closed_at)->toBeNull();

    Artisan::call('qresto:close-idle-table-sessions');

    $session->refresh();

    expect($session->closed_at)->not->toBeNull()
        ->and($session->isActive())->toBeFalse();
});

it('keeps recently active table sessions open', function (): void {
    $session = createIdleCommandTestSession(
        $this->table,
        'idle-command-active',
        5,
    );

    Artisan::call('qresto:close-idle-table-sessions');

    $session->refresh();

    expect($session->closed_at)->toBeNull()
        ->and($session->isActive())->toBeTrue();
});

it('does not change the closing time of already closed sessions', function (): void {
    $session = createIdleCommandTestSession(
        $this->table,
        'idle-command-already-closed',
        35,
    );

    $closedAt = now()->subMinutes(10)->startOfSecond();

    $session->update([
        'closed_at' => $closedAt,
    ]);

    Artisan::call('qresto:close-idle-table-sessions');

    $session->refresh();

    expect($session->closed_at->equalTo($closedAt))->toBeTrue();
});
it('closes idle sessions with no last seen timestamp', function (): void {
    $session = createIdleCommandTestSession(
        $this->table,
        'idle-command-no-last-seen',
        35,
    );

    $session->update([
        'last_seen_at' => null,
    ]);

    Artisan::call('qresto:close-idle-table-sessions');

    $session->refresh();

    expect($session->closed_at)->not->toBeNull()
        ->and($session->isActive())->toBeFalse();
});
