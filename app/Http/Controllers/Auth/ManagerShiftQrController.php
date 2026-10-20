<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagerShiftQrController extends Controller
{
    public function index(Request $request): Response
    {
        $manager = $request->user();

        abort_unless(
            $manager !== null
                && $manager->is_active
                && $manager->hasRole('manager')
                && $manager->can(
                    Capability::MANAGE_WAITER_ACCOUNTS->value
                )
                && $manager->restaurant_id !== null,
            403,
        );

        $restaurant = $manager->restaurant;

        abort_unless($restaurant !== null, 403);

        $staff = User::query()
            ->where('restaurant_id', $manager->restaurant_id)
            ->where('is_active', true)
            ->whereKeyNot($manager->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('manager/shift-qr', [
            'staff' => $staff,
            'restaurantSlug' => $restaurant->slug,
            'shiftQrToken' => $request->session()->pull(
                'shift_qr_token'
            ),
        ]);
    }
}
