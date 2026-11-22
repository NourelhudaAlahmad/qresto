<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PATCH /app/orders/{order}/assign: a manager hands an active order to
 * another waiter of the restaurant (#26), with an order event for the trail.
 */
class OrderAssignmentController extends Controller
{
    public function update(Request $request, Order $order): RedirectResponse
    {
        $manager = $request->user();

        abort_unless(
            $manager
                && $manager->can(Capability::MANAGE_WAITER_ACCOUNTS->value),
            403,
        );

        abort_unless(
            (int) $order->restaurant_id === (int) $manager->restaurant_id,
            404,
        );

        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer'],
        ]);

        $waiter = User::query()
            ->whereKey($validated['assigned_user_id'])
            ->where('restaurant_id', $manager->restaurant_id)
            ->where('is_active', true)
            ->role('waiter')
            ->first();

        if ($waiter === null) {
            throw ValidationException::withMessages([
                'assigned_user_id' => __('messages.orders.reassign_waiter'),
            ]);
        }

        DB::transaction(function () use ($order, $manager, $waiter): void {
            $locked = Order::query()
                ->where('restaurant_id', $manager->restaurant_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (in_array($locked->status, [
                OrderStatus::PAID,
                OrderStatus::CANCELLED,
            ], true)) {
                throw ValidationException::withMessages([
                    'order' => __('messages.orders.reassign_active_only'),
                ]);
            }

            $previousWaiterId = $locked->assigned_user_id;

            if ((int) $previousWaiterId === (int) $waiter->id) {
                return;
            }

            $locked->assigned_user_id = max(1, (int) $waiter->id);
            $locked->save();

            $locked->events()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'actor_id' => $manager->id,
                'actor_kind' => 'staff',
                'occurred_at' => now(),
                'meta' => [
                    'action' => 'waiter_reassigned',
                    'from_waiter_id' => $previousWaiterId,
                    'to_waiter_id' => $waiter->id,
                ],
            ]);
        });

        return back();
    }
}
