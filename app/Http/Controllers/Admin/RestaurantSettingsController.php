<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHoursRequest;
use App\Http\Requests\Admin\UpdateRestaurantDetailsRequest;
use App\Models\Restaurant;
use App\Models\RestaurantHour;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The card that owns the numbers every other screen quotes (#29): details,
 * opening hours and tables, all behind can:manage_restaurant_config.
 */
class RestaurantSettingsController extends Controller
{
    /** Monday first, as the guest landing groups its hours. */
    private const WEEK = [1, 2, 3, 4, 5, 6, 0];

    public function edit(Request $request): Response
    {
        $restaurant = $this->restaurant($request);

        $hours = $restaurant->hours()->get()->keyBy('day_of_week');

        $openSessionTables = TableSession::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->whereNull('closed_at')
            ->get()
            ->filter(fn (TableSession $session): bool => $session->isActive())
            ->pluck('restaurant_table_id')
            ->unique()
            ->all();

        $unpaidTables = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('is_paid', false)
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id')
            ->all();

        return Inertia::render('staff/admin/restaurant', [
            'restaurant' => [
                'name' => $restaurant->name,
                'tagline' => $restaurant->tagline,
                'cuisine' => $restaurant->cuisine,
                'address' => $restaurant->address,
                'phone' => $restaurant->phone,
                'timezone' => $restaurant->timezone,
                'currency' => $restaurant->currency,
                'service_charge_pct' => (float) $restaurant->service_charge_pct,
                'default_locale' => $restaurant->default_locale,
                'supported_locales' => array_values((array) $restaurant->supported_locales),
            ],
            'hours' => array_map(function (int $day) use ($hours): array {
                /** @var RestaurantHour|null $hour */
                $hour = $hours->get($day);
                $closed = $hour === null || $hour->opens_at === null || $hour->closes_at === null;

                return [
                    'day_of_week' => $day,
                    'closed' => $closed,
                    'opens_at' => $closed ? null : substr((string) $hour->opens_at, 0, 5),
                    'closes_at' => $closed ? null : substr((string) $hour->closes_at, 0, 5),
                ];
            }, self::WEEK),
            'tables' => $restaurant->tables()
                ->orderBy('sort_order')
                ->orderBy('number')
                ->get()
                ->map(fn (RestaurantTable $table): array => [
                    'id' => $table->id,
                    'number' => (string) $table->number,
                    'seats' => (int) $table->seats,
                    'sort_order' => (int) $table->sort_order,
                    'qr_url' => route('table.show', $table->qr_token),
                    'busy' => in_array($table->id, $openSessionTables, true),
                    'unpaid' => in_array($table->id, $unpaidTables, true),
                ])
                ->values()
                ->all(),
            'options' => [
                'currencies' => array_values((array) config('qresto.currencies', [])),
                'locales' => collect((array) config('qresto.locales', []))
                    ->map(fn ($label, $code): array => ['code' => (string) $code, 'label' => (string) $label])
                    ->values()
                    ->all(),
                'timezones' => DateTimeZone::listIdentifiers(),
            ],
        ]);
    }

    public function update(UpdateRestaurantDetailsRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurant($request);

        $restaurant->forceFill($request->validated())->save();

        return back()->with('success', 'Restaurant details saved.');
    }

    public function updateHours(UpdateHoursRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurant($request);

        DB::transaction(function () use ($restaurant, $request): void {
            foreach ($request->days() as $day) {
                RestaurantHour::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'day_of_week' => $day['day_of_week']],
                    ['opens_at' => $day['opens_at'], 'closes_at' => $day['closes_at']],
                );
            }
        });

        return back()->with('success', 'Opening hours saved.');
    }

    private function restaurant(Request $request): Restaurant
    {
        /** @var User $user */
        $user = $request->user();

        $restaurant = $user->restaurant;

        abort_unless($restaurant instanceof Restaurant, 403);

        return $restaurant;
    }
}
