<?php

namespace App\Providers;

use App\CurrentRestaurant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentRestaurant::class, function () {
            return new CurrentRestaurant;
        });
    }

    public function boot(): void
    {
        $this->configureGuestRateLimits();
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
