<?php

namespace App\Http\Responses;

use App\Services\Auth\StaffLoginDestination;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class StaffLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $destination = app(StaffLoginDestination::class)
            ->for($request->user());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->to($destination);
    }
}
