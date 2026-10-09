<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
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

        $serviceRequests = collect();

        if ($user->can('viewAny', RestaurantTable::class)) {
            $serviceRequests = ServiceRequest::query()
                ->where('status', 'pending')
                ->whereHas('table', function ($query) use ($user): void {
                    $query->where(
                        'restaurant_id',
                        $user->restaurant_id,
                    );
                })
                ->with('table:id,number,state')
                ->latest('requested_at')
                ->get();
        }

        $liveOrders = LiveResponse::make($orders);
        $liveServiceRequests = LiveResponse::make($serviceRequests);

        // Keep the existing orders-only version when no service
        // requests are pending, preserving current polling behavior.
        $version = $liveOrders['version'];

        if ($serviceRequests->isNotEmpty()) {
            $version .= '|'.$liveServiceRequests['version'];
        }

        // Both live datasets must be checked before returning 204.
        if (request()->query('version') === $version) {
            return response()->noContent();
        }

        $liveOrders['version'] = $version;

        return Inertia::render('dashboard', [
            'liveOrders' => $liveOrders,
            'liveServiceRequests' => $liveServiceRequests,
        ]);
    }
}
