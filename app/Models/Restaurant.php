<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'cuisine',
        'description',
        'address',
        'phone',
        'lat',
        'lng',
        'currency',
        'service_charge_pct',
        'default_locale',
        'supported_locales',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'supported_locales' => 'array',
            'service_charge_pct' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(RestaurantHour::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function isOpenAt(CarbonInterface $dateTime): bool
    {
        $localDateTime = $dateTime->copy()->setTimezone($this->timezone);
        $dayOfWeek = $localDateTime->dayOfWeek;

        $currentDayHours = $this->hours()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (
            $currentDayHours !== null
            && $currentDayHours->opens_at !== null
            && $currentDayHours->closes_at !== null
        ) {
            $opensAt = $localDateTime->copy()->setTimeFromTimeString(
                $currentDayHours->opens_at,
            );

            $closesAt = $localDateTime->copy()->setTimeFromTimeString(
                $currentDayHours->closes_at,
            );

            if ($closesAt->lessThanOrEqualTo($opensAt)) {
                $closesAt->addDay();
            }

            if ($localDateTime->betweenIncluded($opensAt, $closesAt)) {
                return true;
            }
        }

        $previousDay = ($dayOfWeek + 6) % 7;

        $previousDayHours = $this->hours()
            ->where('day_of_week', $previousDay)
            ->first();

        if (
            $previousDayHours === null
            || $previousDayHours->opens_at === null
            || $previousDayHours->closes_at === null
        ) {
            return false;
        }

        $opensAt = $localDateTime
            ->copy()
            ->subDay()
            ->setTimeFromTimeString($previousDayHours->opens_at);

        $closesAt = $localDateTime
            ->copy()
            ->setTimeFromTimeString($previousDayHours->closes_at);

        if ($closesAt->lessThanOrEqualTo($opensAt)) {
            $closesAt->addDay();
        }

        return $localDateTime->betweenIncluded($opensAt, $closesAt);
    }

    protected function openNow(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->isOpenAt(now()),
        );
    }
}
