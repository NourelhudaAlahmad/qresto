<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per change to a staff account, written by App\Actions\Staff.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int|null $actor_id
 * @property int $user_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property Carbon $created_at
 */
class StaffAudit extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DEACTIVATED = 'deactivated';

    public const REACTIVATED = 'reactivated';

    public const INVITED = 'invited';

    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'actor_id',
        'user_id',
        'action',
        'changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
