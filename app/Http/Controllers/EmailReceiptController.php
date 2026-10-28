<?php

namespace App\Http\Controllers;

use App\Mail\ReceiptMail;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\Floor\TableAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

final class EmailReceiptController extends Controller
{
    public function store(
        Request $request,
        RestaurantTable $table,
        Order $order,
    ): RedirectResponse {
        $user = $request->user();

        app(TableAccess::class)->authorize($user, $table);

        abort_unless(
            (int) $order->restaurant_id === (int) $table->restaurant_id
                && (int) $order->table_id === (int) $table->id,
            404,
        );

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
        ]);

        $order->load([
            'restaurant',
            'table',
            'lines',
            'payments' => fn ($query) => $query->orderBy('id'),
        ]);

        Mail::to($validated['email'])->queue(
            new ReceiptMail($order),
        );

        return back()->with(
            'success',
            'Receipt email queued successfully.',
        );
    }
}
