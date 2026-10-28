<?php

namespace App\Notifications;

use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IntegrationFailing extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Integration $integration,
        public readonly string $health,
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
            'type' => 'integration_failing',
            'integration_id' => $this->integration->id,
            'kind' => $this->integration->kind,
            'name' => $this->integration->name,
            'health' => $this->health,
            'detail' => $this->integration->detail,
            'message' => $this->health === 'unknown'
                ? "{$this->integration->name} has stopped reporting."
                : "{$this->integration->name} is failing: ".($this->integration->detail ?? 'no detail'),
        ];
    }
}
