<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'label',
        'price_delta',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => MoneyCast::class,
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
