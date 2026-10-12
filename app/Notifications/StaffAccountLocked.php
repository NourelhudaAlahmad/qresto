<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StaffAccountLocked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $lockedUser,
        public readonly int $minutes = 10,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'staff_account_locked',
            'user_id' => $this->lockedUser->id,
            'user_name' => $this->lockedUser->name,
            'restaurant_id' => $this->lockedUser->restaurant_id,
            'locked_until' => $this->lockedUser->locked_until?->toIso8601String(),
            'minutes' => $this->minutes,
            'message' => "Staff account {$this->lockedUser->name} was locked after 3 failed sign-in attempts.",
        ];
    }
}
