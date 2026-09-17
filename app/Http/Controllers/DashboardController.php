<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\LiveResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(): InertiaResponse|Response
    {
        $user = request()->user();

        $query = Order::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['table', 'assignedUser'])
            ->latest('updated_at');

        if (! $user->can('viewAny', Order::class)) {
            $query->where('assigned_user_id', $user->id);
        }

        $orders = $query->get();

        if (
            LiveResponse::unchanged(
                $orders,
                request()->query('version'),
            )
        ) {
            return response()->noContent();
        }

        return Inertia::render('dashboard', [
            'liveOrders' => LiveResponse::make($orders),
        ]);
    }
}
