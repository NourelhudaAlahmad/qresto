<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\StaffAccountLockout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAccountCanAuthenticate
{
    public function __construct(
        private readonly StaffAccountLockout $lockout,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('two-factor-challenge')) {
            return $next($request);
        }
        $userId = $request->session()->get('login.id');

        if ($userId === null) {
            return $next($request);
        }

        $user = User::query()->whereKey($userId)->first();

        if ($user === null || ! $user->is_active) {
            $request->session()->forget([
                'login.id',
                'login.remember',
            ]);

            throw ValidationException::withMessages([
                'code' => __('messages.auth.account_unavailable'),
            ]);
        }

        if ($this->lockout->isLocked($user)) {
            $minutes = $this->lockout->remainingMinutes($user);

            $request->session()->forget([
                'login.id',
                'login.remember',
            ]);

            throw ValidationException::withMessages([
                'code' => __('messages.auth.locked_for', ['minutes' => $minutes]),
            ]);
        }

        return $next($request);
    }
}
