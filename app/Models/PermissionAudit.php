<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'role',
        'permission',
        'granted',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
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
}
