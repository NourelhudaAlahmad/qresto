<?php

namespace App\Support;

/**
 * The development defaults that break a restaurant when deployed as-is
 * (#59). Each one is silent until it bites: a waiter who never receives
 * an invitation, a card approved by the fake gateway, a floor of polling
 * screens queued behind one SQLite write lock.
 *
 * In production, EnsureDeploymentIsSafe refuses to serve while any
 * problem stands; `qresto:check-deploy` and `php artisan about` list them
 * anywhere.
 */
class DeploymentCheck
{
    /**
     * What must change before this configuration can run a service.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG is true: an error page shows stack traces and configuration to guests. Set APP_DEBUG=false.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'APP_URL is not https: table QR codes, secure cookies and wallet payments need it. Set APP_URL=https://your-domain.';
        }

        $connection = (string) config('database.default');

        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            $problems[] = 'DB_CONNECTION is sqlite: every poll, session write and queued job waits on one file lock. Use mysql, mariadb or pgsql.';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $problems[] = 'MAIL_MAILER is '.config('mail.default').': staff invitations and password resets are never sent. Configure SMTP or a mail service.';
        }

        $driver = (string) config('qresto.payments.driver');

        if ($driver === 'fake') {
            $problems[] = 'QRESTO_PAYMENT_DRIVER is fake: every card is approved and no money moves. Set QRESTO_PAYMENT_DRIVER=stripe.';
        }

        if ($driver === 'stripe') {
            foreach (['key' => 'STRIPE_KEY', 'secret' => 'STRIPE_SECRET', 'webhook_secret' => 'STRIPE_WEBHOOK_SECRET'] as $key => $variable) {
                if (blank(config("qresto.payments.stripe.{$key}"))) {
                    $problems[] = "{$variable} is empty: card payments cannot be taken or confirmed.";
                }
            }
        }

        return $problems;
    }

    /**
     * Whether a production request should be refused while problems stand.
     * QRESTO_ENFORCE_DEPLOY_CHECKS=false is the escape hatch for a
     * deliberate exception, such as a single-device demo.
     */
    public function isEnforced(): bool
    {
        return app()->isProduction() && (bool) config('qresto.deploy.enforce', true);
    }

    /**
     * "test", "live", or "not set", from the secret key's prefix.
     */
    public function stripeMode(): string
    {
        $secret = (string) config('qresto.payments.stripe.secret');

        return match (true) {
            str_starts_with($secret, 'sk_live_'), str_starts_with($secret, 'rk_live_') => 'live',
            str_starts_with($secret, 'sk_test_'), str_starts_with($secret, 'rk_test_') => 'test',
            default => 'not set',
        };
    }
}
