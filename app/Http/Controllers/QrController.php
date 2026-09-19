<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmTableSessionRequest;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\User;
use App\Services\TableSessionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class QrController extends Controller
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
    ) {}

    public function show(string $qrToken): Response
    {
        $session = $this->tableSessionService->startFromQrToken($qrToken);

        $session->load(['restaurant', 'table']);

        $table = $session->table;

        abort_if($table === null, 404);

        $waiter = $this->assignedWaiter($table);

        $response = Inertia::render('guest/table', [
            'restaurant' => [
                'name' => $session->restaurant?->name,
            ],
            'table' => [
                'id' => $table->id,
                'number' => $table->number,
                'seats' => $table->seats,
            ],
            'assigned_waiter' => $waiter === null
                ? null
                : [
                    'name' => $waiter->name,
                    'initials' => $waiter->initials,
                ],
            'qr_token' => $qrToken,
        ])->toResponse(request());

        $response->headers->setCookie(
            $this->sessionCookie($session->token),
        );

        return $response;
    }

    public function confirm(
        ConfirmTableSessionRequest $request,
        string $qrToken,
    ): RedirectResponse {
        $session = $this->tableSessionService
            ->startFromQrToken($qrToken);

        $session->update([
            'guest_name' => $request->validated('first_name'),
            'last_seen_at' => now(),
        ]);

        return redirect()
            ->route('menu')
            ->withCookie(
                $this->sessionCookie($session->token),
            );
    }

    public function tables(string $qrToken): Response
    {
        $session = $this->tableSessionService
            ->startFromQrToken($qrToken);

        $restaurant = $session->restaurant;

        abort_if($restaurant === null, 404);

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('sort_order')
            ->get([
                'id',
                'number',
                'seats',
                'state',
                'qr_token',
            ])
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'number' => $table->number,
                'seats' => $table->seats,
                'state' => $table->state->value,
                'url' => route('table.show', [
                    'qrToken' => $table->qr_token,
                ]),
            ]);

        $response = Inertia::render('guest/tables', [
            'current_table_id' => $session->restaurant_table_id,
            'tables' => $tables,
        ])->toResponse(request());

        $response->headers->setCookie(
            $this->sessionCookie($session->token),
        );

        return $response;
    }

    private function assignedWaiter(RestaurantTable $table): ?User
    {
        $shift = Shift::query()
            ->with('user')
            ->where('restaurant_id', $table->restaurant_id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('tables', function ($query) use ($table): void {
                $query->where('tables.id', $table->id);
            })
            ->first();

        return $shift?->user;
    }

    private function sessionCookie(string $token): Cookie
    {
        $cookieName = config(
            'qresto.table_session_cookie',
            'qresto_table_session',
        );

        if (! is_string($cookieName) || $cookieName === '') {
            $cookieName = 'qresto_table_session';
        }

        return cookie(
            name: $cookieName,
            value: $token,
            minutes: (int) config(
                'qresto.table_session_idle_minutes',
                30,
            ),
            path: '/',
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
