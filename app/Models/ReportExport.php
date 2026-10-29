<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property int|null $user_id
 * @property string $format
 * @property string|null $preset
 * @property Carbon $range_start
 * @property Carbon $range_end
 * @property int $row_count
 * @property Carbon $generated_at
 */
class ReportExport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id', 'user_id', 'format', 'preset',
        'range_start', 'range_end', 'row_count', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'date',
            'range_end' => 'date',
            'generated_at' => 'datetime',
            'row_count' => 'integer',
        ];
    }
}
