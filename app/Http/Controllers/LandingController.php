<?php

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantResource;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Support\LandingCache;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function show(?Restaurant $restaurant = null): Response|RedirectResponse
    {
        $restaurant ??= Restaurant::query()->firstOrFail();

        $locale = app()->getLocale();
        $cacheVersion = LandingCache::version($restaurant->id);

        $cacheKey = sprintf(
            'landing:%d:%s:%d',
            $restaurant->id,
            $locale,
            $cacheVersion,
        );

        $payload = Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            function () use ($restaurant): array {
                $localNow = Carbon::now($restaurant->timezone);

                $hours = $restaurant->hours()
                    ->orderBy('day_of_week')
                    ->get()
                    ->map(
                        fn ($hour): array => [
                            'day' => $this->dayName($hour->day_of_week),
                            'day_of_week' => $hour->day_of_week,
                            'opens_at' => $hour->opens_at,
                            'closes_at' => $hour->closes_at,
                            'is_today' => $hour->day_of_week === $localNow->dayOfWeek,
                        ],
                    )
                    ->values()
                    ->all();

                $featuredQuery = MenuItem::withoutGlobalScope('restaurant')
                    ->where('restaurant_id', $restaurant->id)
                    ->availableNow();

                $chefPick = (clone $featuredQuery)
                    ->where('chef_flag', true)
                    ->orderBy('sort_order')
                    ->first();

                $additionalItem = (clone $featuredQuery)
                    ->where('chef_flag', false)
                    ->orderBy('sort_order')
                    ->first();

                $featuredItems = collect([
                    $chefPick,
                    $additionalItem,
                ])
                    ->filter()
                    ->map(
                        fn (MenuItem $item): array => [
                            'id' => $item->id,
                            'name' => $item->translated_name,
                            'price' => $this->formatPrice(
                                $item->getRawOriginal('price'),
                                $restaurant->currency,
                            ),
                            'chef_flag' => $item->chef_flag,
                            'photo_path' => $item->photo_path,
                        ],
                    )
                    ->values()
                    ->all();

                return [
                    'hours' => $hours,
                    'featured_items' => $featuredItems,
                ];
            },
        );

        return Inertia::render('guest/landing', [
            'restaurant' => (new RestaurantResource($restaurant))->resolve(),
            ...$payload,
            'open_now' => $restaurant->isOpenAt(now()),
            'translations' => trans('landing'),
        ]);
    }

    private function dayName(int $dayOfWeek): string
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ][$dayOfWeek];
    }

    private function formatPrice(mixed $minorUnits, string $currency): string
    {
        $amount = ((int) $minorUnits) / 100;

        return match ($currency) {
            'USD' => '$'.number_format($amount, 2),
            'EUR' => '€'.number_format($amount, 2),
            'TRY' => '₺'.number_format($amount, 2),
            default => $currency.' '.number_format($amount, 2),
        };
    }
}
