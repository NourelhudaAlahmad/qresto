<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ElapsedTime
{
    /**
     * @return 'normal'|'warn'|'late'
     */
    public static function level(CarbonInterface $since): string
    {
        $minutes = $since->diffInMinutes(now());

        if ($minutes >= (int) config('qresto.sla.late_minutes')) {
            return 'late';
        }

        if ($minutes >= (int) config('qresto.sla.warn_minutes')) {
            return 'warn';
        }

        return 'normal';
    }
}
