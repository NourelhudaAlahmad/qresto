<?php

namespace App\Http\Middleware;

use App\CurrentRestaurant;
use App\Models\TableSession;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTableSession
{
    public function __construct(
        private readonly CurrentRestaurant $currentRestaurant,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $cookieName = (string) config(
            'qresto.table_session_cookie',
            'qresto_table_session',
        );

        $token = $request->cookie($cookieName);

        if (! is_string($token) || $token === '') {
            return redirect()
                ->route('home')
                ->with('error', __('landing.session.missing'));
        }

        $session = TableSession::withoutGlobalScopes()
            ->with(['restaurant', 'table'])
            ->where('token', $token)
            ->first();

        if ($session === null || ! $session->isActive()) {
            return redirect()
                ->route('home')
                ->with('error', __('landing.session.expired'));
        }

        $restaurant = $session->restaurant;

        if ($restaurant === null) {
            return redirect()
                ->route('home')
                ->with('error', __('landing.session.unavailable'));
        }

        $this->currentRestaurant->set($restaurant);

        $session->touchActivity();

        $request->attributes->set('tableSession', $session);

        return $next($request);
    }
}
