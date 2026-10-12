<?php

namespace App\Services\Auth;

use App\Enums\Capability;
use App\Models\ShiftSignInToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShiftQrService
{
    public function issue(User $manager, User $staff): string
    {
        if (
            ! $manager->is_active
            || ! $manager->hasRole('manager')
            || ! $manager->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            || $manager->restaurant_id === null
            || $manager->restaurant_id !== $staff->restaurant_id
            || ! $staff->is_active
        ) {
            throw ValidationException::withMessages([
                'staff' => 'Not authorized to issue a shift QR for this staff member.',
            ]);
        }

        $rawToken = Str::random(64);

        ShiftSignInToken::query()->create([
            'restaurant_id' => $manager->restaurant_id,
            'user_id' => $staff->id,
            'token' => hash('sha256', $rawToken),
            'issued_by' => $manager->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        return $rawToken;
    }

    public function consume(
        string $rawToken,
        int $restaurantId,
    ): ?User {
        if (strlen($rawToken) !== 64) {
            return null;
        }

        return DB::transaction(function () use (
            $rawToken,
            $restaurantId,
        ): ?User {
            $token = ShiftSignInToken::query()
                ->where('token', hash('sha256', $rawToken))
                ->lockForUpdate()
                ->first();

            if (
                $token === null
                || $token->restaurant_id !== $restaurantId
                || $token->isUsed()
                || $token->isExpired()
            ) {
                return null;
            }

            $staff = User::query()
                ->whereKey($token->user_id)
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->first();

            if (
                $staff === null
                || app(StaffAccountLockout::class)->isLocked($staff)
            ) {
                return null;
            }

            $token->forceFill([
                'used_at' => now(),
                'used_by' => $staff->id,
            ])->save();

            return $staff;
        });
    }
}
