<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\Floor\TableAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ReceiptController extends Controller
{
    public function show(
        Request $request,
        RestaurantTable $table,
        Order $order,
    ): View {
        $user = $request->user();

        app(TableAccess::class)->authorize($user, $table);

        abort_unless(
            (int) $order->restaurant_id
                === (int) $table->restaurant_id
                && (int) $order->table_id
                === (int) $table->id,
            404,
        );

        $order->load([
            'restaurant',
            'lines',
            'payments' => fn ($query) => $query->orderBy('id'),
        ]);

        $payments = $order->payments
            ->filter(
                fn ($payment): bool => $payment->status === PaymentStatus::PAID->value,
            )
            ->values();

        $paidMinor = (int) $payments->sum(
            fn ($payment): int => $payment->amount->amount(),
        );

        $totalMinor = $order->total->amount();

        return view('receipts.order', [
            'order' => $order,
            'table' => $table,
            'payments' => $payments,
            'totalMinor' => $totalMinor,
            'paidMinor' => $paidMinor,
            'balanceMinor' => max(0, $totalMinor - $paidMinor),
        ]);
    }
}
