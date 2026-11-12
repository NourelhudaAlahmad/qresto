<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One delivered gateway event, kept only so a replay is recognised and
 * ignored (StripeWebhookController). Not a record of anything.
 */
class WebhookEvent extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'type',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    /**
     * Past Stripe's retry and resend window, a replay can no longer
     * arrive, so the guard row has nothing left to guard (#57).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'received_at',
            '<',
            now()->subDays((int) config('qresto.retention.webhook_events_days', 30)),
        );
    }
}
