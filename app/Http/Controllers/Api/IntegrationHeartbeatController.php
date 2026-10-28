<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Integrations\IntegrationMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /api/integrations/{integration}/heartbeat
 *
 * Called by a KDS screen on an interval, or by a printer agent with its
 * paper state in `detail`. Authenticated by the integration's own bearer
 * token, so one device cannot report for another.
 */
class IntegrationHeartbeatController extends Controller
{
    public function __invoke(
        Request $request,
        int $integration,
        IntegrationMonitor $monitor,
    ): JsonResponse {
        $model = Integration::query()->withoutGlobalScopes()->find($integration);
        $token = (string) $request->bearerToken();

        // Same answer for "no such integration" and "wrong token", so the
        // endpoint does not reveal which ids exist.
        if ($model === null || $token === '' || ! $model->tokenMatches($token)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(Integration::REPORTED)],
            'detail' => ['nullable', 'string', 'max:160'],
            'meta' => ['nullable', 'array'],
        ]);

        $monitor->recordHeartbeat(
            $model,
            $data['status'],
            $data['detail'] ?? null,
            $data['meta'] ?? null,
        );

        return response()->json([
            'health' => $model->fresh()?->health(),
            'received_at' => now()->toIso8601String(),
        ]);
    }
}
