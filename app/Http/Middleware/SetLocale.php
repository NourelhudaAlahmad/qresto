<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        $availableLocales = $restaurant === null
            ? ['en', 'ar']
            : ($restaurant->supported_locales ?? ['en', 'ar']);

        $availableLocales = array_values(
            array_intersect($availableLocales, ['en', 'ar']),
        );

        if ($availableLocales === []) {
            $availableLocales = ['en', 'ar'];
        }

        $locale = null;

        // Explicit language switch.
        if ($request->is('locale') && $request->isMethod('post')) {
            $locale = $request->input('locale');
        }

        // Saved user preference.
        $locale ??= $user?->locale;

        // Saved device preference.
        $locale ??= $request->session()->get('locale');
        $locale ??= $request->cookie('locale');

        // Browser preference.
        if ($locale === null) {
            $browserLocale = $request->getPreferredLanguage($availableLocales);

            if ($browserLocale !== null) {
                $locale = $browserLocale;
            }
        }

        // Restaurant default.
        if ($locale === null) {
            $locale = $restaurant === null
                ? 'en'
                : ($restaurant->default_locale ?? 'en');
        }

        if (! in_array($locale, $availableLocales, true)) {
            $restaurantDefault = $restaurant === null
                ? null
                : $restaurant->default_locale;

            $locale = in_array($restaurantDefault, $availableLocales, true)
                ? $restaurantDefault
                : $availableLocales[0];
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
