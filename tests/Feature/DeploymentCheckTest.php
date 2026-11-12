<?php

use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Support\DeploymentCheck;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/**
 * A configuration a restaurant can run on. The database connection is a
 * name the test never queries, so only its driver is read.
 */
function productionReadyConfig(): void
{
    config([
        'app.debug' => false,
        'app.url' => 'https://qresto.example',
        'database.connections.deploy_check' => ['driver' => 'pgsql'],
        'database.default' => 'deploy_check',
        'mail.default' => 'smtp',
        'qresto.payments.driver' => 'stripe',
        'qresto.payments.stripe.key' => 'pk_live_123',
        'qresto.payments.stripe.secret' => 'sk_live_123',
        'qresto.payments.stripe.webhook_secret' => 'whsec_123',
        'qresto.deploy.enforce' => true,
    ]);
}

function inProduction(): void
{
    app()->detectEnvironment(fn (): string => 'production');
}

// RefreshDatabase rolls back on the default connection after each test.
afterEach(fn () => config(['database.default' => 'sqlite']));

it('finds nothing to fix in a production-ready configuration', function () {
    productionReadyConfig();

    expect(app(DeploymentCheck::class)->problems())->toBe([]);
});

it('names each development default that breaks a service', function (array $override, string $named) {
    productionReadyConfig();
    config($override);

    $problems = app(DeploymentCheck::class)->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain($named);
})->with([
    'debug on' => [['app.debug' => true], 'APP_DEBUG'],
    'plain http' => [['app.url' => 'http://qresto.example'], 'APP_URL'],
    'sqlite' => [['database.default' => 'sqlite'], 'DB_CONNECTION is sqlite'],
    'mail to the log' => [['mail.default' => 'log'], 'staff invitations and password resets are never sent'],
    'mail to an array' => [['mail.default' => 'array'], 'MAIL_MAILER is array'],
    'fake payments' => [['qresto.payments.driver' => 'fake'], 'every card is approved'],
    'no Stripe key' => [['qresto.payments.stripe.key' => ''], 'STRIPE_KEY'],
    'no Stripe secret' => [['qresto.payments.stripe.secret' => null], 'STRIPE_SECRET'],
    'no webhook secret' => [['qresto.payments.stripe.webhook_secret' => ''], 'STRIPE_WEBHOOK_SECRET'],
]);

it('flags the .env.example defaults all at once', function () {
    config([
        'app.debug' => true,
        'app.url' => 'http://localhost:8000',
        'database.default' => 'sqlite',
        'mail.default' => 'log',
        'qresto.payments.driver' => 'fake',
    ]);

    expect(app(DeploymentCheck::class)->problems())->toHaveCount(5);
});

it('refuses to serve production with a development default, health check included', function () {
    productionReadyConfig();
    config(['mail.default' => 'log']);
    inProduction();

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/up'))->toThrow(
        RuntimeException::class,
        'QResto will not serve production with this configuration',
    );
});

it('answers the guest with the error page, not the reason', function () {
    productionReadyConfig();
    config(['mail.default' => 'log']);
    inProduction();

    $this->get('/up')
        ->assertServerError()
        ->assertDontSee('MAIL_MAILER');
});

it('serves production once the configuration is right', function () {
    productionReadyConfig();
    inProduction();

    $this->get('/up')->assertOk();
});

it('leaves local and test environments alone', function () {
    config(['mail.default' => 'log', 'qresto.payments.driver' => 'fake']);

    expect(app(DeploymentCheck::class)->isEnforced())->toBeFalse();

    $this->get('/up')->assertOk();
});

it('can be switched off for a deliberate exception', function () {
    productionReadyConfig();
    config(['mail.default' => 'log', 'qresto.deploy.enforce' => false]);
    inProduction();

    $this->get('/up')->assertOk();
});

it('lists what to fix from the command line and fails until it is fixed', function () {
    config(['mail.default' => 'log', 'qresto.payments.driver' => 'fake']);

    $this->artisan('qresto:check-deploy')
        ->expectsOutputToContain('must change before production')
        ->expectsOutputToContain('MAIL_MAILER is log')
        ->expectsOutputToContain('QRESTO_PAYMENT_DRIVER is fake')
        ->assertFailed();

    productionReadyConfig();

    $this->artisan('qresto:check-deploy')
        ->expectsOutputToContain('Ready for production')
        ->assertSuccessful();
});

it('shows the QResto configuration in php artisan about', function () {
    config(['mail.default' => 'log', 'qresto.payments.stripe.secret' => 'sk_test_123']);

    Artisan::call('about', ['--only' => 'qresto', '--json' => true]);

    // Laravel snake-cases the section name: "QResto" → "q_resto".
    $about = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['q_resto'];

    expect($about['production_checks'])->toContain('TO FIX')
        ->and($about['stripe_mode'])->toBe('test')
        ->and($about['retention'])->toBe('sessions 30 d, carts 7 d, webhook events 30 d')
        ->and($about)->toHaveKeys(['payment_driver', 'currency', 'stripe_webhook_secret', 'checks_enforced']);
});

it('reads the Stripe mode from the secret key', function (?string $secret, string $mode) {
    config(['qresto.payments.stripe.secret' => $secret]);

    expect(app(DeploymentCheck::class)->stripeMode())->toBe($mode);
})->with([
    'live' => ['sk_live_abc', 'live'],
    'restricted live' => ['rk_live_abc', 'live'],
    'test' => ['sk_test_abc', 'test'],
    'unset' => [null, 'not set'],
]);

describe('qresto:create-restaurant', function () {
    beforeEach(function () {
        $this->seed(RolesSeeder::class);
        Notification::fake();
    });

    it('creates the first restaurant and invites its admin by email', function () {
        $this->artisan('qresto:create-restaurant', [
            'name' => 'Lokanta Deniz',
            '--admin-name' => 'Selin Kaya',
            '--admin-email' => 'selin@deniz.example',
            '--timezone' => 'Europe/Istanbul',
            '--currency' => 'try',
        ])
            ->expectsOutputToContain('Invitation sent to selin@deniz.example')
            ->assertSuccessful();

        $restaurant = Restaurant::query()->where('slug', 'lokanta-deniz')->sole();
        $admin = User::query()->where('email', 'selin@deniz.example')->sole();

        expect($restaurant->timezone)->toBe('Europe/Istanbul')
            ->and($restaurant->currency)->toBe('TRY')
            ->and($admin->restaurant_id)->toBe($restaurant->id)
            ->and($admin->hasRole('admin'))->toBeTrue()
            ->and($admin->is_active)->toBeTrue()
            ->and($admin->initials)->toBe('SK');

        Notification::assertSentTo($admin, StaffInvitation::class);
    });

    it('refuses an email that already has an account, and a bad timezone', function () {
        User::factory()->create(['email' => 'taken@deniz.example']);

        $this->artisan('qresto:create-restaurant', [
            'name' => 'Lokanta Deniz',
            '--admin-name' => 'Selin Kaya',
            '--admin-email' => 'taken@deniz.example',
            '--timezone' => 'Mars/Olympus',
        ])
            ->expectsOutputToContain('email has already been taken')
            ->expectsOutputToContain('timezone')
            ->assertFailed();

        expect(Restaurant::query()->count())->toBe(0);
    });

    it('gives a second restaurant of the same name its own slug', function () {
        Restaurant::factory()->create(['slug' => 'lokanta-deniz']);

        $this->artisan('qresto:create-restaurant', [
            'name' => 'Lokanta Deniz',
            '--admin-name' => 'Selin Kaya',
            '--admin-email' => 'selin@deniz.example',
        ])->assertSuccessful();

        expect(Restaurant::query()->where('slug', 'lokanta-deniz-2')->exists())->toBeTrue();
    });
});
