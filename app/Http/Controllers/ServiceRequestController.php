<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestKind;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ServiceRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $session = $this->tableSession($request);

        $validated = $request->validate([
            'kind' => [
                'required',
                'string',
                Rule::in([
                    ServiceRequestKind::WATER->value,
                    ServiceRequestKind::BREAD->value,
                    ServiceRequestKind::BILL->value,
                    ServiceRequestKind::WAITER->value,
                ]),
            ],
            'table_id' => [
                'sometimes',
                'integer',
            ],
        ]);

        if (
            array_key_exists('table_id', $validated)
            && (int) $validated['table_id']
            !== (int) $session->restaurant_table_id
        ) {
            abort(403);
        }

        $kind = ServiceRequestKind::from(
            $validated['kind'],
        );

        DB::transaction(function () use (
            $session,
            $kind,
        ): void {
            $session->refresh();

            $table = $session->table()
                ->lockForUpdate()
                ->first();

            abort_if(
                $table === null,
                403,
            );

            $alreadyOpen = ServiceRequest::query()
                ->where(
                    'table_session_id',
                    $session->id,
                )
                ->where(
                    'table_id',
                    $table->id,
                )
                ->where(
                    'kind',
                    $kind->value,
                )
                ->where(
                    'status',
                    'pending',
                )
                ->lockForUpdate()
                ->exists();

            abort_if(
                $alreadyOpen,
                429,
                'This request is already open.',
            );

            ServiceRequest::query()->create([
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'kind' => $kind,
                'status' => 'pending',
                'requested_at' => now(),
                'acknowledged_by' => null,
                'acknowledged_at' => null,
            ]);

            if ($kind === ServiceRequestKind::BILL) {
                $table->requestBill();
            }

            $session->forceFill([
                'last_seen_at' => now(),
            ])->save();
        });

        return back()->with(
            'success',
            'Request sent.',
        );
    }

    public function acknowledge(
        Request $request,
        ServiceRequest $serviceRequest,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        abort_if(
            $user === null,
            403,
        );

        $serviceRequest->loadMissing(
            'table:id,restaurant_id',
        );

        abort_if(
            $serviceRequest->table === null
                || (int) $serviceRequest->table->restaurant_id
                !== (int) $user->restaurant_id,
            403,
        );

        if ($serviceRequest->status === 'pending') {
            $serviceRequest->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $user->id,
                'acknowledged_at' => now(),
            ]);
        }

        return back()->with(
            'success',
            'Request acknowledged.',
        );
    }

    private function tableSession(
        Request $request,
    ): TableSession {
        /** @var TableSession|null $session */
        $session = $request
            ->attributes
            ->get('tableSession');

        abort_if(
            $session === null,
            403,
        );

        return $session;
    }
}
