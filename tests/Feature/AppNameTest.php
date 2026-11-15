<?php

use App\Models\User;
use App\Notifications\StaffInvitation;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\SentMessage;

uses(RefreshDatabase::class);

/*
 * #67: the product is QResto everywhere a person reads its name: the tab
 * title, the mail From line, the error page. The framework default must not
 * reach a fresh install through .env.example or any config fallback.
 */

/**
 * The environment a fresh install starts from: .env.example, with
 * ${APP_NAME} resolved the way Dotenv resolves it.
 *
 * @return array<string, string|null>
 */
function shippedEnvironment(): array
{
    return Dotenv::parse((string) file_get_contents(base_path('.env.example')));
}

/**
 * Loads a config file with APP_NAME and MAIL_FROM_NAME unset, so only the
 * file's own fallback can answer.
 *
 * @return array<string, mixed>
 */
function configWithoutAppName(string $file): array
{
    $keys = ['APP_NAME', 'MAIL_FROM_NAME', 'LOG_SLACK_USERNAME', 'CACHE_PREFIX', 'REDIS_PREFIX', 'SESSION_COOKIE'];
    $saved = [];

    foreach ($keys as $key) {
        $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    try {
        return require config_path("{$file}.php");
    } finally {
        foreach ($saved as $key => [$env, $server, $process]) {
            if ($env !== null) {
                $_ENV[$key] = $env;
            }

            if ($server !== null) {
                $_SERVER[$key] = $server;
            }

            if ($process !== false) {
                putenv("{$key}={$process}");
            }
        }
    }
}

/** Sends mail the way a fresh install is configured to. */
function useShippedMailFrom(): void
{
    $shipped = shippedEnvironment();

    config([
        'app.name' => $shipped['APP_NAME'],
        'mail.default' => 'array',
        'mail.from.name' => $shipped['MAIL_FROM_NAME'],
    ]);

    Mail::purge('array');
}

function lastSentFromName(): string
{
    /** @var SentMessage $sent */
    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last();

    return $sent->getOriginalMessage()->getFrom()[0]->getName();
}

it('ships QResto as the app, tab and mail name in .env.example', function () {
    $shipped = shippedEnvironment();

    expect($shipped['APP_NAME'])->toBe('QResto')
        ->and($shipped['VITE_APP_NAME'])->toBe('QResto')
        ->and($shipped['MAIL_FROM_NAME'])->toBe('QResto');
});

it('falls back to QResto in every config that reads APP_NAME', function () {
    expect(configWithoutAppName('app')['name'])->toBe('QResto')
        ->and(configWithoutAppName('mail')['from']['name'])->toBe('QResto')
        ->and(configWithoutAppName('logging')['channels']['slack']['username'])->toBe('QResto')
        ->and(configWithoutAppName('cache')['prefix'])->toBe('qresto-cache-')
        ->and(configWithoutAppName('session')['cookie'])->toBe('qresto-session')
        ->and(configWithoutAppName('database')['redis']['options']['prefix'])->toBe('qresto-database-');
});

it('falls back to QResto for the tab title, in the browser and in the HTML shell', function () {
    expect(file_get_contents(resource_path('js/app.tsx')))
        ->toContain("import.meta.env.VITE_APP_NAME || 'QResto'")
        ->not->toContain("'Laravel'")
        ->and(file_get_contents(resource_path('views/app.blade.php')))
        ->toContain("config('app.name', 'QResto')")
        ->not->toContain("'Laravel'");
});

it('sends the staff invitation from QResto', function () {
    useShippedMailFrom();

    $waiter = User::factory()->create(['name' => 'Omar Faruk']);
    $waiter->notify(new StaffInvitation('token', 2880, 'Sara Malik'));

    expect(lastSentFromName())->toBe('QResto');
});

it('sends the password reset from QResto', function () {
    useShippedMailFrom();

    $user = User::factory()->create();

    expect(Password::sendResetLink(['email' => $user->email]))->toBe(Password::RESET_LINK_SENT)
        ->and(lastSentFromName())->toBe('QResto');
});
