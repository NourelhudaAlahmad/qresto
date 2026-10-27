<?php

namespace App\Services\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money and menu health for the owner's view (#28).
 *
 * Revenue month to date = settled payments in the month minus refunds
 * issued in the month. Gateway fees are estimated from
 * qresto.payments.gateway_fee_pct on in-app payments (card and wallet).
 */
class AdminDashboardSummary
{
    private const APP_METHODS = [
        PaymentMethod::CARD,
        PaymentMethod::WALLET,
    ];

    /**
     * @return array{
     *     revenue: array{mtd_minor: int, settled_minor: int, refunds_minor: int, fees_minor: int, net_of_fees_minor: int},
     *     payment_split: array{app_minor: int, cash_minor: int, pos_minor: int, app_share: int},
     *     menu: array{live_count: int, sold_out: int, scheduled: int},
     *     failed_payments: array{count: int, recovered: int}
     * }
     */
    public function for(Restaurant $restaurant, ?Carbon $now = null): array
    {
        $timezone = $restaurant->timezone ?: (string) config('app.timezone');
        $now = ($now ?? now())->copy()->timezone($timezone);

        $from = $now->copy()->startOfMonth()->utc();
        $to = $now->copy()->endOfMonth()->utc();

        $payments = fn () => DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.restaurant_id', $restaurant->id);

        // A refunded payment was still settled first, so it counts here and
        // its refund is subtracted below.
        $settledByMethod = $payments()
            ->whereIn('payments.status', [
                PaymentStatus::PAID->value,
                PaymentStatus::REFUNDED->value,
            ])
            ->whereBetween('payments.paid_at', [$from, $to])
            ->groupBy('payments.method')
            ->selectRaw('payments.method AS method, SUM(payments.amount) AS total')
            ->pluck('total', 'method')
            ->map(fn ($total): int => (int) $total);

        $refunds = (int) $payments()
            ->where('payments.refunded_amount', '>', 0)
            ->whereBetween('payments.refunded_at', [$from, $to])
            ->sum('payments.refunded_amount');

        $settled = (int) $settledByMethod->sum();

        $appMinor = 0;

        foreach (self::APP_METHODS as $method) {
            $appMinor += (int) ($settledByMethod[$method->value] ?? 0);
        }

        $feePct = (float) config('qresto.payments.gateway_fee_pct', 0);
        $fees = (int) round($appMinor * $feePct / 100);

        $mtd = $settled - $refunds;

        $failed = $payments()
            ->where('payments.status', PaymentStatus::FAILED->value)
            ->whereBetween('payments.created_at', [$from, $to])
            ->select('payments.order_id', 'orders.is_paid')
            ->get();

        $menu = MenuItem::query()->where('restaurant_id', $restaurant->id);

        return [
            'revenue' => [
                'mtd_minor' => $mtd,
                'settled_minor' => $settled,
                'refunds_minor' => $refunds,
                'fees_minor' => $fees,
                'net_of_fees_minor' => $mtd - $fees,
            ],
            'payment_split' => [
                'app_minor' => $appMinor,
                'cash_minor' => (int) ($settledByMethod[PaymentMethod::CASH->value] ?? 0),
                'pos_minor' => (int) ($settledByMethod[PaymentMethod::POS->value] ?? 0),
                'app_share' => $settled > 0 ? (int) round($appMinor * 100 / $settled) : 0,
            ],
            'menu' => [
                'live_count' => (clone $menu)
                    ->where('is_available', true)
                    ->where('is_scheduled', false)
                    ->count(),
                'sold_out' => (clone $menu)->where('is_available', false)->count(),
                'scheduled' => (clone $menu)->where('is_scheduled', true)->count(),
            ],
            'failed_payments' => [
                'count' => $failed->count(),
                // Recovered when the order was settled another way.
                'recovered' => $failed->filter(fn ($row): bool => (bool) $row->is_paid)->count(),
            ],
        ];
    }
}
