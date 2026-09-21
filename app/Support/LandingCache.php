<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class LandingCache
{
    public static function version(int $restaurantId): int
    {
        return (int) Cache::get(
            self::key($restaurantId),
            1,
        );
    }

    public static function invalidate(int $restaurantId): void
    {
        Cache::increment(self::key($restaurantId));
    }

    private static function key(int $restaurantId): string
    {
        return "landing:version:{$restaurantId}";
    }
}
