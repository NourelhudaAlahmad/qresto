<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign-in already refuses inactive accounts. This ends a session that was
 * open when the account was deactivated, on its very next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strict: a freshly created model may not carry the column default
        // in memory yet (null), and that must not log anyone out.
        if ($user !== null && ($user->is_active === false || $user->deactivated_at !== null)) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'This account has been deactivated.');
        }

        return $next($request);
    }
}
