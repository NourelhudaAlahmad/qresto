<?php

namespace App\Http\Middleware;

use App\Services\CapabilitySnapshot;
use App\Services\NavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $restaurant = $user?->restaurant;

        $availableLocales = $restaurant->supported_locales
            ?? ['en', 'ar'];

        $locale = app()->getLocale();

        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        $shared = [
            ...parent::share($request),

            'name' => config('app.name'),

            'locale' => $locale,

            'dir' => $dir,

            'available_locales' => $availableLocales,

            'qresto' => [
                'sla' => [
                    'warn_minutes' => (int) config(
                        'qresto.sla.warn_minutes',
                        14,
                    ),
                    'late_minutes' => (int) config(
                        'qresto.sla.late_minutes',
                        25,
                    ),
                ],
            ],
        ];

        if ($user === null) {
            return [
                ...$shared,

                'auth' => [
                    'user' => null,
                ],

                'nav' => [],

                'capabilities' => [],

                'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                    || $request->cookie('sidebar_state') === 'true',
            ];
        }

        $capabilitySnapshot = app(CapabilitySnapshot::class);
        $capabilities = $capabilitySnapshot->get($user);

        return [
            ...$shared,

            'auth' => [
                'user' => $user,
            ],

            'nav' => app(NavigationBuilder::class)->for(
                $user,
                $capabilities,
            ),

            'capabilities' => $capabilities,

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
