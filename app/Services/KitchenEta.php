<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

final class KitchenEta
{
    /**
     * @return array{min: int, max: int}|null
     */
    public function forOrder(
        Order $order,
    ): ?array {
        if (
            in_array(
                $order->status,
                [
                    OrderStatus::SERVED,
                    OrderStatus::PAID,
                    OrderStatus::CANCELLED,
                ],
                true,
            )
        ) {
            return null;
        }

        $order->loadMissing([
            'lines.menuItem:id,prep_minutes',
        ]);

        $prepMinutes = $order->lines
            ->map(
                static function ($line): int {
                    $menuItem = $line->getRelation(
                        'menuItem',
                    );

                    return max(
                        1,
                        (int) data_get(
                            $menuItem,
                            'prep_minutes',
                            10,
                        ),
                    );
                },
            )
            ->max() ?? 10;

        $queueDepth = Order::query()
            ->where(
                'restaurant_id',
                $order->restaurant_id,
            )
            ->whereKeyNot($order->id)
            ->whereIn('status', [
                OrderStatus::PLACED->value,
                OrderStatus::PENDING->value,
                OrderStatus::PREPARING->value,
            ])
            ->when(
                $order->placed_at !== null,
                function (
                    Builder $query,
                ) use ($order): void {
                    $query->where(
                        function (
                            Builder $query,
                        ) use ($order): void {
                            $query
                                ->where(
                                    'placed_at',
                                    '<',
                                    $order->placed_at,
                                )
                                ->orWhere(
                                    function (
                                        Builder $query,
                                    ) use ($order): void {
                                        $query
                                            ->where(
                                                'placed_at',
                                                $order->placed_at,
                                            )
                                            ->where(
                                                'id',
                                                '<',
                                                $order->id,
                                            );
                                    },
                                );
                        },
                    );
                },
            )
            ->count();

        $queueDelay = min(
            $queueDepth * 2,
            20,
        );

        $min = max(
            5,
            $prepMinutes + $queueDelay - 2,
        );

        $max = $min + max(
            4,
            (int) ceil(
                $prepMinutes * 0.35,
            ),
        );

        return [
            'min' => $min,
            'max' => $max,
        ];
    }
}
