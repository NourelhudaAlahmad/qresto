<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly int $expiresInMinutes,
        public readonly string $invitedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(User $notifiable): string
    {
        return route('staff.invitation.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $hours = intdiv($this->expiresInMinutes, 60);

        return (new MailMessage)
            ->subject('Set your QResto password')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->invitedBy} added you to tonight's shift.")
            ->action('Set your password', $this->url($notifiable))
            ->line("This link works once and expires in {$hours} hours.");
    }
}
