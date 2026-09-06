<?php

namespace App\Providers;

use App\CurrentRestaurant;
use App\Listeners\StoreCapabilitySnapshot;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, StoreCapabilitySnapshot::class);
    }
}
