<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Carbon;

/**
 * Health is derived, never stored: a row cannot claim to be healthy because
 * nothing has updated it. A heartbeat older than its kind's threshold reads
 * "unknown", whatever the device last said.
 */
class IntegrationHealth
{
    public const OPERATIONAL = 'operational';

    public const DEGRADED = 'degraded';

    public const FAILING = 'failing';

    public const UNKNOWN = 'unknown';

    /** Healths that mean "something is wrong" for alerting. */
    public const BAD = [self::FAILING, self::UNKNOWN];

    public function of(Integration $integration, ?Carbon $now = null): string
    {
        if ($this->isStale($integration, $now)) {
            return self::UNKNOWN;
        }

        return match ($integration->status) {
            'ok' => self::OPERATIONAL,
            'warning' => self::DEGRADED,
            'failing' => self::FAILING,
            default => self::UNKNOWN,
        };
    }

    public function isStale(Integration $integration, ?Carbon $now = null): bool
    {
        if ($integration->last_heartbeat_at === null) {
            return true;
        }

        $now ??= Carbon::now();

        return $integration->last_heartbeat_at->lt(
            $now->copy()->subSeconds($this->thresholdSeconds($integration->kind)),
        );
    }

    public function thresholdSeconds(string $kind): int
    {
        /** @var array<string, int> $thresholds */
        $thresholds = config('qresto.integrations.stale_after_seconds', []);

        return (int) ($thresholds[$kind] ?? 300);
    }
}
