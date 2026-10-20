<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Auth\ShiftQrService;
use App\Services\Auth\StaffLoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ShiftQrAuthController extends Controller
{
    public function issue(
        Request $request,
        ShiftQrService $shiftQr,
    ): RedirectResponse {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $manager */
        $manager = $request->user();

        $staff = User::query()->whereKey((int) $data['user_id'])->firstOrFail();

        $rawToken = $shiftQr->issue($manager, $staff);

        // Only the token hash is stored in the database.
        return redirect()
            ->route('manager.shift-qr.index')
            ->with('shift_qr_token', $rawToken);
    }

    public function store(
        Request $request,
        ShiftQrService $shiftQr,
        StaffLoginDestination $destination,
    ): RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'restaurant_slug' => ['required', 'string'],
        ]);

        $restaurant = Restaurant::query()
            ->where('slug', $data['restaurant_slug'])
            ->first();

        if ($restaurant === null) {
            throw ValidationException::withMessages([
                'token' => 'Invalid or expired shift QR.',
            ]);
        }

        $staff = $shiftQr->consume(
            $data['token'],
            $restaurant->id,
        );

        if ($staff === null) {
            throw ValidationException::withMessages([
                'token' => 'Invalid, expired, or already used shift QR.',
            ]);
        }

        // Shift QR must not bypass Fortify two-factor authentication.
        if (
            ! empty($staff->two_factor_secret)
            && $staff->two_factor_confirmed_at !== null
        ) {
            $request->session()->regenerate();

            $request->session()->put([
                'login.id' => $staff->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($staff);

        $request->session()->regenerate();

        return redirect()->to(
            $destination->for($staff),
        );
    }
}
