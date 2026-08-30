<?php

namespace App\Providers;

use App\CurrentRestaurant;
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
        //
    }
}
