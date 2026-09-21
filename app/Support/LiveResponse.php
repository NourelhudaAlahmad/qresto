<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class LiveResponse
{
    /**
     * @param  Collection<int, mixed>  $items
     * @return array{
     *     items: Collection<int, mixed>,
     *     version: string
     * }
     */
    public static function make(Collection $items): array
    {
        $maxUpdatedAt = $items
            ->filter(fn (mixed $item): bool => isset($item->updated_at))
            ->max('updated_at');

        $version = sprintf(
            '%s:%d',
            $maxUpdatedAt?->format('Y-m-d H:i:s.u') ?? 'none',
            $items->count(),
        );

        return [
            'items' => $items,
            'version' => $version,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    public static function version(Collection $items): string
    {
        return self::make($items)['version'];
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    public static function unchanged(Collection $items, ?string $cursor): bool
    {
        return $cursor !== null && self::version($items) === $cursor;
    }
}
