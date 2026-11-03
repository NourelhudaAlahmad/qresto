<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #51: "Keep me signed in on this device" covers a whole shift. A remembered
 * login gets config('qresto.auth.remembered_session_minutes') instead of the
 * stock session lifetime; an unticked one keeps the stock lifetime.
 *
 * Runs before the session starts, so the session store and its cookie both
 * pick up the longer lifetime.
 */
class ExtendRememberedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->remembered($request)) {
            config([
                'session.lifetime' => (int) config('qresto.auth.remembered_session_minutes'),
            ]);
        }

        return $next($request);
    }

    private function remembered(Request $request): bool
    {
        // The same name SessionGuard::getRecallerName() builds. Resolving the
        // guard here would start the session store with the stock lifetime.
        $recaller = 'remember_web_'.sha1(SessionGuard::class);

        if ($request->cookies->has($recaller)) {
            return true;
        }

        // The sign-in that ticks the box, before the remember cookie exists.
        return $request->isMethod('POST')
            && $request->is('login')
            && $request->boolean('remember');
    }
}
