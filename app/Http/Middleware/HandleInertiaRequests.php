<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\CapabilitySnapshot;
use App\Services\NavigationBuilder;
use App\Services\Reports\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            // Copy for shared components (status pills, the guest tab bar,
            // help and call-waiter sheets), in the active language. Lazy, so
            // partial reloads that ask for one prop do not resend it.
            'i18n' => fn (): array => [
                'guest' => trans('guest'),
                'orders' => trans('orders'),
                'tables' => trans('tables'),
                'staff' => trans('staff'),
            ],

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

        $roleLabel = Role::tryFrom($role ?? '')?->label() ?? __('staff.roles.staff');

        $pageMeta = match ($request->route()?->getName()) {
            'app.overview',
            'manager.overview' => [
                'staff.pages.overview',
                'staff.pages.overview_sub',
            ],

            'app.orders' => [
                'staff.pages.live_orders',
                'staff.pages.live_orders_sub',
            ],

            'app.floor' => [
                'staff.pages.floor',
                'staff.pages.floor_sub',
            ],

            'app.reports' => [
                'staff.pages.reports',
                'staff.pages.reports_sub',
            ],

            'app.my-orders',
            'waiter.orders' => [
                'staff.pages.my_orders',
                'staff.pages.my_orders_sub',
            ],

            'app.pass' => [
                'staff.pages.kitchen_display',
                'staff.pages.pass_screen',
            ],
            'app.menu' => [
                'staff.pages.menu',
                'staff.pages.menu_sub',
            ],
            'app.settings.restaurant' => [
                'staff.pages.restaurant',
                'staff.pages.restaurant_sub',
            ],
            'app.system',
            'admin.system' => [
                'staff.pages.system',
                'staff.pages.system_sub',
            ],

            'app.pass',
            'kitchen.pass' => [
                'staff.pages.kitchen_pass',
                'staff.pages.kitchen_pass_sub',
            ],

            'manager.shift-qr.index' => [
                'staff.pages.shift_qr',
                'staff.pages.shift_qr_sub',
            ],

            'dashboard' => [
                'staff.pages.dashboard',
                'staff.pages.dashboard_sub',
            ],

            default => [
                'staff.pages.staff',
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
        // "Pass screen · 2 stations": the stations with work on the pass.
        $viewer = $request->user();

        if ($restaurant !== null
            && $request->route()?->getName() === 'app.pass'
            && $viewer?->hasRole('runner')
            && ! $viewer->hasAnyRole(['kitchen', 'manager', 'admin'])) {
            return 'Runner · ready to collect';
        }

        if ($restaurant !== null && $request->route()?->getName() === 'app.pass') {
            $stations = DB::table('order_lines')
                ->join('orders', 'orders.id', '=', 'order_lines.order_id')
                ->where('orders.restaurant_id', $restaurant->id)
                ->whereIn('orders.status', ['pending', 'preparing'])
                ->whereNotNull('order_lines.station')
                ->distinct()
                ->count('order_lines.station');

            return 'Pass screen · '.$stations.' '.($stations === 1 ? 'station' : 'stations');
        }

        // "01 – 23 Aug · all waiters · USD", as the design's reports title.
        if ($restaurant !== null && $request->route()?->getName() === 'app.reports') {
            $preset = (string) $request->query('preset', 'month');
            $preset = in_array($preset, SalesReport::PRESETS, true) ? $preset : 'month';

            try {
                $range = app(SalesReport::class)->range(
                    $restaurant,
                    $preset,
                    $request->query('start'),
                    $request->query('end'),
                );
            } catch (\Throwable) {
                return __($fallback);
            }

            $start = $range['start']->format($range['start']->isSameMonth($range['end']) ? 'd' : 'd M');

            return "{$start} – {$range['end']->format('d M')} · all waiters · {$restaurant->currency}";
        }

        if ($restaurant !== null && $request->route()?->getName() === 'app.menu') {
            $dishes = MenuItem::query()->where('restaurant_id', $restaurant->id);

            return sprintf(
                '%d categories · %d dishes · %d sold out',
                MenuCategory::query()->where('restaurant_id', $restaurant->id)->count(),
                (clone $dishes)->count(),
                (clone $dishes)->where('is_available', false)->count(),
            );
        }

        if ($restaurant === null || $request->route()?->getName() !== 'app.system') {
            return __($fallback);
        }

        $area = trim((string) Str::afterLast((string) $restaurant->address, ','));

        return collect([$restaurant->name, $area, __('owner account')])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' · ');
    }
}
