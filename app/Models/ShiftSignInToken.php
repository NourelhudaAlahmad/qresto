<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSignInToken extends Model
{
    use BelongsToRestaurant;
    use MassPrunable;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'token',
        'issued_by',
        'expires_at',
        'used_at',
        'used_by',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Restaurant, $this> */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * A code lives five minutes; its row is kept for the retention period
     * after that as a record of who signed in, then pruned.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->withoutGlobalScopes()->where(
            'expires_at',
            '<',
            now()->subDays((int) config('qresto.retention.shift_sign_in_tokens_days', 30)),
        );
    }
}
