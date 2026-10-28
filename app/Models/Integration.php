<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use App\Services\Integrations\IntegrationHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $kind
 * @property string $name
 * @property string|null $status
 * @property string|null $detail
 * @property Carbon|null $last_heartbeat_at
 * @property array<string, mixed>|null $meta
 * @property string|null $token_hash
 * @property string|null $alerted_health
 */
class Integration extends Model
{
    use BelongsToRestaurant;

    public const KINDS = ['gateway', 'kds', 'printer'];

    public const REPORTED = ['ok', 'warning', 'failing'];

    protected $fillable = [
        'restaurant_id',
        'kind',
        'name',
        'status',
        'detail',
        'last_heartbeat_at',
        'meta',
        'alerted_health',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Issue a new device token. Returns the plain token, which is shown
     * once; only its hash is stored, and the previous token stops working.
     */
    public function issueToken(): string
    {
        $token = 'qri_'.Str::random(48);

        $this->forceFill(['token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function tokenMatches(string $token): bool
    {
        return $this->token_hash !== null
            && hash_equals($this->token_hash, hash('sha256', $token));
    }

    /**
     * operational | degraded | failing | unknown, from the heartbeat age.
     */
    public function health(?Carbon $now = null): string
    {
        return app(IntegrationHealth::class)->of($this, $now);
    }
}
