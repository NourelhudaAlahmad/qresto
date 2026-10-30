<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\Integrations\IntegrationMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The kitchen pass display (#33). Behind can:change_order_status, so the
 * kitchen account and anyone else who may move an order can bump.
 */
class KitchenDisplayController extends Controller
{
    /** The pass shows what the kitchen still has to cook. */
    private const OPEN = [OrderStatus::PENDING, OrderStatus::PREPARING];

    public function index(Request $request): InertiaResponse
    {
        $station = $request->validate([
            'station' => ['nullable', 'string', Rule::in(MenuCategory::STATIONS)],
        ])['station'] ?? null;

        return Inertia::render('staff/kitchen/pass', [
            'station' => $station,
            'stations' => MenuCategory::STATIONS,
            'tickets' => fn (): array => $this->tickets($this->user($request), $station),
            'sla' => [
                'warn_minutes' => (int) config('qresto.sla.warn_minutes', 14),
                'late_minutes' => (int) config('qresto.sla.late_minutes', 25),
            ],
            'pollIntervalMs' => (int) config('qresto.live.kds_interval', 4) * 1000,
            'heartbeatIntervalMs' => 30_000,
            'now' => now()->valueOf(),
        ]);
    }

    /**
     * pending → preparing → ready, with the bumping account as the actor.
     */
    public function bump(Request $request, Order $order): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless((int) $order->restaurant_id === (int) $user->restaurant_id, 404);

        $next = match ($order->status) {
            OrderStatus::PENDING => OrderStatus::PREPARING,
            OrderStatus::PREPARING => OrderStatus::READY,
            default => null,
        };

        if ($next === null) {
            throw ValidationException::withMessages([
                'order' => "{$order->code} is no longer on the pass.",
            ]);
        }

        $order->transitionTo($next, $user);

        return back();
    }

    /**
     * The pass screen reporting itself to system health (#30) while it is
     * open. Session-authenticated, so the browser needs no device token.
     */
    public function heartbeat(Request $request, IntegrationMonitor $monitor): Response
    {
        $user = $this->user($request);

        $integration = Integration::query()->firstOrCreate(
            ['restaurant_id' => $user->restaurant_id, 'kind' => 'kds'],
            ['name' => 'Kitchen display'],
        );

        $monitor->recordHeartbeat($integration, 'ok', 'pass screen up');

        return response()->noContent();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tickets(User $user, ?string $station): array
    {
        $now = now();

        $orders = Order::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, self::OPEN))
            ->with([
                'table:id,number',
                'tableSession:id,guest_name',
                'lines' => fn ($query) => $query->orderBy('id'),
                'lines.menuItem' => fn ($query) => $query->withTrashed()->with('category:id,station'),
            ])
            ->orderBy('placed_at')
            ->orderBy('id')
            ->get();

        $tickets = [];

        foreach ($orders as $order) {
            $lines = $order->lines
                ->map(fn (OrderLine $line): array => [
                    'qty' => (int) $line->qty,
                    'name' => (string) $line->name_snapshot,
                    'note' => $line->note !== null && $line->note !== '' ? (string) $line->note : null,
                    'station' => $line->station ?? $line->menuItem?->category?->station,
                ])
                ->filter(fn (array $line): bool => $station === null || $line['station'] === $station)
                ->values()
                ->all();

            // Filtered to one station, a ticket with nothing for it is not
            // that station's work.
            if ($lines === []) {
                continue;
            }

            $firedAt = $order->placed_at ?? $order->created_at;

            $tickets[] = [
                'id' => (int) $order->id,
                'code' => (string) $order->code,
                'status' => $order->status->value,
                'table' => $order->table?->number,
                'guest' => $order->guest_name ?? $order->tableSession?->guest_name,
                'fired_at' => $firedAt?->toIso8601String(),
                'elapsed_minutes' => $firedAt === null ? 0 : (int) floor($firedAt->diffInMinutes($now, true)),
                'lines' => $lines,
            ];
        }

        return $tickets;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
