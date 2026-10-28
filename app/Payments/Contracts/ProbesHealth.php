<?php

namespace App\Payments\Contracts;

/**
 * A gateway that can answer "are you up?" for the system health card (#30).
 */
interface ProbesHealth
{
    /**
     * @return array{status: 'ok'|'warning'|'failing', detail: string}
     */
    public function probe(): array;
}
