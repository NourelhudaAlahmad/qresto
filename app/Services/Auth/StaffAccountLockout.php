<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffAccountLockout
{
    public function remainingMinutes(User $user): int
    {
        if ($user->locked_until === null || $user->locked_until->isPast()) {
            return 0;
        }

        $seconds = now()->diffInSeconds($user->locked_until);

        return max(1, (int) ceil($seconds / 60));
    }

    public function isLocked(User $user): bool
    {
        return $this->remainingMinutes($user) > 0;
    }

    public function recordFailure(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            /** @var User $account */
            $account = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            if ($this->isLocked($account)) {
                return false;
            }

            $attempts = $account->locked_until?->isPast()
                ? 1
                : $account->failed_attempts + 1;

            $account->failed_attempts = $attempts;

            if ($attempts >= 3) {
                $account->locked_until = now()->addMinutes(10);
            } else {
                $account->locked_until = null;
            }

            $account->save();

            $user->setRawAttributes($account->getAttributes(), true);

            return $attempts === 3;
        });
    }

    public function recordSuccess(User $user): void
    {
        $user->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();
    }
}
