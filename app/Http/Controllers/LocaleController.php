<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:en,ar'],
        ]);

        $locale = $request->string('locale')->toString();

        $user = $request->user();

        if ($user !== null) {
            $restaurant = $user->restaurant;

            if (
                $restaurant !== null
                && ! in_array($locale, $restaurant->supported_locales ?? [], true)
            ) {
                abort(422, 'The selected locale is not supported by this restaurant.');
            }

            $user->update([
                'locale' => $locale,
            ]);
        }

        $request->session()->put('locale', $locale);

        return back()->withCookie(
            cookie(
                'locale',
                $locale,
                60 * 24 * 365,
            ),
        );
    }
}
