<?php

namespace App\Providers;

use App\CurrentRestaurant;
use App\Enums\Capability;
use App\Models\User;
use App\Support\DeploymentCheck;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #35: one Stripe client for the app; tests swap its HTTP client.
        $this->app->bind(StripeClient::class, fn (): StripeClient => new StripeClient([
            'api_key' => (string) config('qresto.payments.stripe.secret'),
        ]));

        $this->app->singleton(CurrentRestaurant::class, function () {
            return new CurrentRestaurant;
        });
    }

    public function boot(): void
    {
        $this->configureGuestRateLimits();
        $this->describeInAbout();

        // A table's bill and receipt: the waiter whose table it is, or the
        // cashier settling across the floor (#34). TableAccess then checks
        // the table itself.
        Gate::define(
            'view-floor-bill',
            fn (User $user): bool => $user->can(Capability::SEE_OWN_TABLES->value)
                || $user->can(Capability::TAKE_PAYMENT->value),
        );
    }

    /**
     * A QResto section in `php artisan about`, so a misconfigured deploy is
     * visible at a glance next to Laravel's own drivers (#59).
     */
    private function describeInAbout(): void
    {
        AboutCommand::add('QResto', function (): array {
            $check = app(DeploymentCheck::class);
            $problems = $check->problems();

            return [
                'Production checks' => $problems === []
                    ? '<fg=green;options=bold>READY</>'
                    : '<fg=red;options=bold>'.count($problems).' TO FIX</> (php artisan qresto:check-deploy)',
                'Checks enforced' => $check->isEnforced() ? 'YES' : 'NO (not production, or QRESTO_ENFORCE_DEPLOY_CHECKS=false)',
                'Currency' => (string) config('qresto.currency'),
                'Payment driver' => (string) config('qresto.payments.driver'),
                'Stripe mode' => $check->stripeMode(),
                'Stripe webhook secret' => filled(config('qresto.payments.stripe.webhook_secret')) ? 'set' : 'not set',
                'Retention' => sprintf(
                    'sessions %d d, carts %d d, webhook events %d d',
                    (int) config('qresto.retention.table_sessions_days'),
                    (int) config('qresto.retention.carts_days'),
                    (int) config('qresto.retention.webhook_events_days'),
                ),
            ];
        });
    }

    /**
     * Guest endpoints have no account to throttle, so each limit is keyed
     * by the table session cookie, falling back to the IP.
     */
    private function configureGuestRateLimits(): void
    {
        $guestKey = function (Request $request): string {
            $token = $request->cookie((string) config('qresto.table_session_cookie'));

            return is_string($token) && $token !== ''
                ? $token
                : (string) $request->ip();
        };

        // Calling the waiter: a few taps are fine, a stream is not.
        RateLimiter::for(
            'guest-service-requests',
            fn (Request $request) => Limit::perMinute(6)->by('sr:'.$guestKey($request)),
        );

        // Promo codes: small enough that guessing a code is impractical.
        RateLimiter::for(
            'guest-promo',
            fn (Request $request) => [
                Limit::perMinute(5)->by('promo:'.$guestKey($request)),
                Limit::perHour(20)->by('promo-ip:'.$request->ip()),
            ],
        );

        RateLimiter::for(
            'guest-checkout',
            fn (Request $request) => Limit::perMinute(10)->by('checkout:'.$guestKey($request)),
        );

        RateLimiter::for(
            'guest-cart',
            fn (Request $request) => Limit::perMinute(60)->by('cart:'.$guestKey($request)),
        );
    }
}
