<?php

namespace App\Services\Integrations;

use App\Enums\Capability;
use App\Models\Integration;
use App\Models\User;
use App\Notifications\IntegrationFailing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Records heartbeats and tells managers when something goes from healthy
 * to failing, once per transition, never on every poll.
 */
class IntegrationMonitor
{
    public function __construct(
        private readonly IntegrationHealth $health,
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordHeartbeat(
        Integration $integration,
        string $status,
        ?string $detail = null,
        ?array $meta = null,
    ): Integration {
        $integration->forceFill([
            'status' => $status,
            'detail' => $detail,
            'meta' => $meta ?? $integration->meta,
            'last_heartbeat_at' => now(),
        ])->save();

        $this->check($integration);

        return $integration;
    }

    /**
     * Compare the derived health with what managers were last told and
     * notify on a healthy → failing (or → unknown) transition. Safe to call
     * as often as you like: it only writes when the health changes side.
     */
    public function check(Integration $integration): void
    {
        DB::transaction(function () use ($integration): void {
            /** @var Integration $locked */
            $locked = Integration::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($integration->id);

            $now = $this->health->of($locked);
            $was = $locked->alerted_health;

            $wasBad = in_array($was, IntegrationHealth::BAD, true);
            $isBad = in_array($now, IntegrationHealth::BAD, true);

            if ($isBad && ! $wasBad && $was !== null) {
                $this->notifyManagers($locked, $now);
            }

            if ($was !== $now && ($isBad !== $wasBad || $was === null)) {
                $locked->forceFill(['alerted_health' => $now])->save();
            }
        });
    }

    public function checkAll(): int
    {
        $checked = 0;

        Integration::query()->withoutGlobalScopes()->each(function (Integration $integration) use (&$checked): void {
            $this->check($integration);
            $checked++;
        });

        return $checked;
    }

    private function notifyManagers(Integration $integration, string $health): void
    {
        $managers = User::query()
            ->where('restaurant_id', $integration->restaurant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value));

        Notification::send($managers, new IntegrationFailing($integration, $health));
    }
}
