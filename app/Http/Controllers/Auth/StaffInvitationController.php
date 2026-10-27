<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The set-password page a new staff member reaches from their invitation.
 * Uses the `staff_invites` broker, so the link has its own (longer) expiry
 * and cannot be confused with a forgot-password token.
 */
class StaffInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/set-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker('staff_invites')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === Password::INVALID_TOKEN
                    ? 'This link has expired or was already used. Ask your manager for a new one.'
                    : __($status),
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password is set. Sign in to start your shift.');
    }
}
