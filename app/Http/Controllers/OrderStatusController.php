<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Services\KitchenEta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderStatusController extends Controller
{
    public function show(
        Request $request,
        Order $order,
        KitchenEta $kitchenEta,
    ): Response {
        $session = $this->tableSession($request);

        abort_if(
            (int) $order->restaurant_id !== (int) $session->restaurant_id
                || (int) $order->table_session_id !== (int) $session->id,
            403,
        );

        $session->loadMissing(
            'table:id,number',
        );

        return Inertia::render('guest/status', [
            'restaurant' => [
                'phone' => $session->restaurant->phone,
            ],

            'session' => [
                'id' => $session->id,
                'active' => $session->isActive(),
            ],

            'cart_count' => fn (): int => (int) (Cart::query()
                ->where('table_session_id', $session->id)
                ->first()
                ?->lines()
                ->whereNull('removed_at')
                ->sum('qty') ?? 0),

            'order' => fn (): array => [
                'id' => $order->id,
                'code' => $order->code,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'currency' => $order->currency,
                'table_number' => $session->table?->number,
            ],

            'assigned_waiter' => function () use ($order): ?array {
                $order->loadMissing(
                    'assignedUser:id,name',
                );

                if ($order->assignedUser === null) {
                    return null;
                }

                return [
                    'id' => $order->assignedUser->id,
                    'name' => $order->assignedUser->name,
                    'initials' => $this->initials(
                        $order->assignedUser->name,
                    ),
                ];
            },

            'lines' => function () use ($order): array {
                $order->loadMissing([
                    'lines' => fn ($query) => $query
                        ->orderBy('id'),
                ]);

                return $order->lines
                    ->map(
                        static fn ($line): array => [
                            'id' => $line->id,
                            'name' => $line->name_snapshot,
                            'qty' => $line->qty,
                            'total' => $line
                                ->line_total
                                ->jsonSerialize(),
                        ],
                    )
                    ->values()
                    ->all();
            },

            'receipt' => fn (): array => [
                'subtotal' => $order
                    ->subtotal
                    ->jsonSerialize(),

                'service_amount' => $order
                    ->service_amount
                    ->jsonSerialize(),

                'tip_amount' => $order
                    ->tip_amount
                    ->jsonSerialize(),

                'discount_amount' => $order
                    ->discount_amount
                    ->jsonSerialize(),

                'total' => $order
                    ->total
                    ->jsonSerialize(),
            ],

            /**
             * This is the volatile slice.
             * Guest polling requests only "live".
             */
            'live' => function () use (
                $order,
                $session,
                $kitchenEta,
            ): array {
                $order->refresh();

                $order->load([
                    'assignedUser:id,name',
                    'events.actor:id,name',

                    'payments' => fn ($query) => $query
                        ->orderByDesc('id'),
                ]);

                $latestPayment = $order
                    ->payments
                    ->first();

                return [
                    'open_requests' => ServiceRequest::query()
                        ->where('table_session_id', $session->id)
                        ->where('table_id', $session->restaurant_table_id)
                        ->where('status', 'pending')
                        ->pluck('kind')
                        ->map(
                            static fn ($kind): string => $kind instanceof \BackedEnum
                                ? (string) $kind->value
                                : (string) $kind,
                        )
                        ->values()
                        ->all(),

                    'status' => $order->status->value,

                    'stage' => $this->guestStage(
                        $order->status->value,
                    ),

                    'events' => $this->trailEvents(
                        $order,
                    ),

                    'eta_minutes' => $kitchenEta
                        ->forOrder($order),

                    'paid' => (bool) $order->is_paid,

                    'payment_method' => $latestPayment?->method,

                    'assigned_waiter' => $order->assignedUser === null
                        ? null
                        : [
                            'id' => $order->assignedUser->id,
                            'name' => $order->assignedUser->name,
                            'initials' => $this->initials(
                                $order->assignedUser->name,
                            ),
                        ],
                ];
            },
        ]);
    }

    private function tableSession(
        Request $request,
    ): TableSession {
        /** @var TableSession|null $session */
        $session = $request
            ->attributes
            ->get('tableSession');

        abort_if(
            $session === null,
            403,
        );

        return $session;
    }

    /**
     * @return list<array{
     *     status: string,
     *     at: string|null,
     *     actor: string|null
     * }>
     */
    private function trailEvents(
        Order $order,
    ): array {
        $events = [
            [
                'status' => 'placed',
                'at' => $order
                    ->placed_at
                    ?->toIso8601String(),
                'actor' => null,
            ],
        ];

        foreach ($order->events as $event) {
            if (
                ! in_array(
                    $event->to_status,
                    [
                        'preparing',
                        'ready',
                        'served',
                    ],
                    true,
                )
            ) {
                continue;
            }

            $actor = $event->actor_id !== null
                ? $event->actor->name
                : $event->actor_kind;

            $events[] = [
                'status' => $event->to_status,
                'at' => $event
                    ->occurred_at
                    ->toIso8601String(),
                'actor' => $actor,
            ];
        }

        return array_values(
            collect($events)
                ->unique('status')
                ->all(),
        );
    }

    private function guestStage(
        string $status,
    ): string {
        return match ($status) {
            'pending' => 'placed',
            'paid' => 'served',
            default => $status,
        };
    }

    private function initials(
        string $name,
    ): string {
        $parts = preg_split(
            '/\s+/u',
            trim($name),
        ) ?: [];

        return collect($parts)
            ->filter()
            ->take(2)
            ->map(
                static fn (string $part): string => mb_strtoupper(
                    mb_substr(
                        $part,
                        0,
                        1,
                    ),
                ),
            )
            ->implode('');
    }
}
