<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * A cart whose session has been quiet past the retention window, open
     * or closed, ordered from or not (#57). Lines and add-ons cascade.
     * Orders copied what they need into order_lines when they were placed.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays((int) config('qresto.retention.carts_days', 7));

        return static::query()->withoutGlobalScopes()->whereIn(
            'table_session_id',
            TableSession::withoutGlobalScopes()->quietSince($cutoff)->select('id'),
        );
    }

    protected $fillable = [
        'restaurant_id',
        'table_session_id',
        'currency',
        'note',
    ];

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return HasMany<CartLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class);
    }
}
