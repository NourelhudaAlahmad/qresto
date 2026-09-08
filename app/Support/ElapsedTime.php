<?php

namespace App\Support;

use Carbon\Carbon;

final class ElapsedTime
{
    public const NORMAL = 'normal';
    public const WARN = 'warn';
    public const LATE = 'late';

    public static function level(Carbon $since): string
    {
        $elapsedMinutes = $since->diffInMinutes(now());

        $warnMinutes = (int) config('qresto.sla.warn_minutes', 14);
        $lateMinutes = (int) config('qresto.sla.late_minutes', 25);

        if ($elapsedMinutes >= $lateMinutes) {
            return self::LATE;
        }

        if ($elapsedMinutes >= $warnMinutes) {
            return self::WARN;
        }

        return self::NORMAL;
    }
}