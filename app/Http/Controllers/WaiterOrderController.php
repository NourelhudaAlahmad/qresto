<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\KitchenOrderVoided;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WaiterOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->can(Capability::SEE_ALL_ORDERS->value)
                || $user->can(Capability::SEE_OWN_TABLES->value)
            ),
            403
        );

        $orders = fn (): array => $this->visibleOrders($request)
            ->map(function (Order $order): array {
                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'status' => $order->status->value,
                    'table' => $order->table?->number,
                    'guest_name' => $order->guest_name,
                    'placed_at' => $order->placed_at?->toIso8601String(),
                    'total_minor' => $order->total?->amount() ?? 0,
                    'currency' => $order->currency,
                    'paid' => $order->is_paid,
                    'lines' => $order->lines
                        ->map(fn ($line): array => [
                            'id' => $line->id,
                            'qty' => $line->qty,
                            'name' => $line->name_snapshot,
                            'note' => $line->note,
                        ])
                        ->all(),
                ];
            })
            ->all();

        return Inertia::render('staff/orders/kanban', [
            'orders' => $orders,
            'canAdvance' => $user->can(
                Capability::CHANGE_ORDER_STATUS->value
            ),
            'canTakePayment' => $user->can(
                Capability::TAKE_PAYMENT->value
            ),
            'warnMinutes' => (int) config(
                'qresto.sla.warn_minutes',
                15
            ),
            'lateMinutes' => (int) config(
                'qresto.sla.late_minutes',
                25
            ),
        ]);
    }

    public function advance(
        Request $request,
        Order $order
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);

        abort_unless(
            $request->user()->can(
                Capability::CHANGE_ORDER_STATUS->value
            ),
            403
        );

        DB::transaction(function () use ($order, $request): void {
            $locked = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $next = match ($locked->status) {
                OrderStatus::PLACED => OrderStatus::PENDING,
                OrderStatus::PENDING => OrderStatus::PREPARING,
                OrderStatus::PREPARING => OrderStatus::READY,
                OrderStatus::READY => OrderStatus::SERVED,
                default => null,
            };

            if ($next === null) {
                throw ValidationException::withMessages([
                    'order' => 'This order cannot be advanced.',
                ]);
            }

            $locked->transitionTo($next, $request->user());
        });

        return back()->with(
            'success',
            'Order status updated.'
        );
    }

    public function void(
        Request $request,
        Order $order
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);

        abort_unless(
            $request->user()->can(
                Capability::CHANGE_ORDER_STATUS->value
            ),
            403
        );

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:500',
            ],
        ]);

        DB::transaction(function () use (
            $order,
            $request,
            $validated
        ): void {
            $locked = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (
                ! $locked->status->canTransitionTo(
                    OrderStatus::CANCELLED
                )
            ) {
                throw ValidationException::withMessages([
                    'order' => 'This order cannot be voided.',
                ]);
            }

            $locked->forceFill([
                'void_reason' => $validated['reason'],
                'voided_by' => $request->user()->id,
                'voided_at' => now(),
            ])->save();

            $locked->transitionTo(
                OrderStatus::CANCELLED,
                $request->user()
            );

            $locked->events()
                ->latest('id')
                ->first()
                ?->update([
                    'meta' => [
                        'reason' => $validated['reason'],
                    ],
                ]);
        });

        // Notify active kitchen staff from this restaurant.
        $kitchenUsers = User::query()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('is_active', true)
            ->role('kitchen')
            ->get();

        Notification::send(
            $kitchenUsers,
            new KitchenOrderVoided(
                (int) $order->id,
                (string) $order->code,
                (string) $validated['reason'],
            ),
        );

        return back()->with(
            'success',
            'Order voided.'
        );
    }

    private function authorizeOrder(
        Request $request,
        Order $order
    ): void {
        $user = $request->user();

        abort_unless(
            $user
                && (int) $order->restaurant_id
                === (int) $user->restaurant_id,
            404
        );

        abort_unless(
            $user->can(Capability::SEE_ALL_ORDERS->value)
                || (
                    $user->can(Capability::SEE_OWN_TABLES->value)
                    && (int) $order->assigned_user_id
                    === (int) $user->id
                ),
            403
        );
    }

    /**
     * @return Collection<int, Order>
     */
    private function visibleOrders(
        Request $request
    ): Collection {
        $user = $request->user();

        $query = Order::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', [
                OrderStatus::PLACED->value,
                OrderStatus::PENDING->value,
                OrderStatus::PREPARING->value,
                OrderStatus::READY->value,
                OrderStatus::SERVED->value,
            ])
            ->with([
                'table:id,number',
                'lines:id,order_id,name_snapshot,qty,note',
            ])
            ->orderBy('placed_at');

        if (
            ! $user->can(
                Capability::SEE_ALL_ORDERS->value
            )
        ) {
            $query->forWaiter($user);
        }

        return $query->get();
    }
}
