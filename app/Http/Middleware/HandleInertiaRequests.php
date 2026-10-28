<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\CapabilitySnapshot;
use App\Services\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $availableLocales = $user === null
            ? ['en', 'ar']
            : ($restaurant->supported_locales ?? ['en', 'ar']);

        $locale = app()->getLocale();

        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        $shared = [
            ...parent::share($request),

            'name' => config('app.name'),

            'flash' => [
                'error' => fn () => $request->session()->get('error'),
            ],

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

                'live' => [
                    'waiter_interval' => (int) config(
                        'qresto.live.waiter_interval',
                        5,
                    ) * 1000,

                    'floor_interval' => (int) config(
                        'qresto.live.floor_interval',
                        8,
                    ) * 1000,

                    'kds_interval' => (int) config(
                        'qresto.live.kds_interval',
                        4,
                    ) * 1000,

                    'guest_status_interval' => (int) config(
                        'qresto.live.guest_status_interval',
                        10,
                    ) * 1000,
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

        $role = $user->getRoleNames()->first();

        $roleLabel = Role::tryFrom($role ?? '')?->label() ?? 'Staff';

        $pageMeta = match ($request->route()?->getName()) {
            'app.overview',
            'manager.overview' => [
                'Overview',
                'Restaurant operations at a glance',
            ],

            'app.orders' => [
                'Live Orders',
                'Current restaurant orders',
            ],

            'app.floor' => [
                'Floor',
                'Restaurant tables and seating',
            ],

            'app.reports' => [
                'Reports',
                'Restaurant reports and analytics',
            ],

            'app.my-orders',
            'waiter.orders' => [
                'My Orders',
                'Your assigned orders and tables',
            ],

            'app.settings.restaurant' => [
                'Restaurant',
                'Details, opening hours, tables and QR codes',
            ],
            'app.system',
            'admin.system' => [
                'System',
                'Restaurant administration',
            ],

            'app.menu' => [
                'Menu',
                'Restaurant menu management',
            ],

            'app.pass',
            'kitchen.pass' => [
                'Kitchen Pass',
                'Live kitchen order feed',
            ],

            'manager.shift-qr.index' => [
                'Shift QR',
                'Issue staff sign-in codes',
            ],

            'dashboard' => [
                'Dashboard',
                'Restaurant overview',
            ],

            default => [
                'Staff',
                '',
            ],
        };

        // Count active orders only when the user has permission
        // to view all orders, and only within their restaurant.
        $openOrders = fn (): ?int => (
            $restaurant !== null
            && in_array(
                Capability::SEE_ALL_ORDERS->value,
                $capabilities,
                true,
            )
            && $user->can(Capability::SEE_ALL_ORDERS->value)
        )
            ? Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->active()
                ->count()
            : null;

        // Count unread notifications for the current user only.
        $unreadNotifications = fn (): int => $user
            ->unreadNotifications()
            ->count();

        $staffLayout = [
            'roleLabel' => $roleLabel,
            'restaurantName' => $restaurant->name ?? '',
            'pageTitle' => __($pageMeta[0]),
            'pageSubtitle' => $this->pageSubtitle($request, $restaurant, $pageMeta[1]),

            // Compatibility with the existing Staff Layout.
            'unreadNotifications' => $unreadNotifications,
        ];

        return [
            ...$shared,

            'auth' => [
                'user' => $user,
            ],

            'nav' => app(NavigationBuilder::class)->for(
                $user,
                $capabilities,
            ),

            'staffLayout' => $staffLayout,

            // Independently reloadable Inertia props.
            'staffOpenOrders' => $openOrders,
            'staffUnreadNotifications' => $unreadNotifications,

            'capabilities' => $capabilities,

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The admin dashboard names the restaurant and its area, as the design
     * does: "Al Bustan · Downtown · owner account". The area is the last
     * part of the address.
     */
    private function pageSubtitle(Request $request, ?Restaurant $restaurant, string $fallback): string
    {
        if ($restaurant === null || $request->route()?->getName() !== 'app.system') {
            return __($fallback);
        }

        $area = trim((string) Str::afterLast((string) $restaurant->address, ','));

        return collect([$restaurant->name, $area, __('owner account')])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' · ');
    }
}
