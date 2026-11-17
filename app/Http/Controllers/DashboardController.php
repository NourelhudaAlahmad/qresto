<?php

namespace App\Http\Controllers;

use App\Services\Auth\StaffLoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /dashboard was the starter kit's page, in no role's navigation (#70).
 * Every role has its own landing screen, so the URL only sends a signed-in
 * user there: an old bookmark, Fortify's home and the settings sidebar
 * still work.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, StaffLoginDestination $destination): RedirectResponse
    {
        return redirect($destination->for($request->user()));
    }
}
