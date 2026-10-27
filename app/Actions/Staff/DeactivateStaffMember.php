<?php

namespace App\Actions\Staff;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StaffAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Deleting a waiter keeps their order history and releases their tables
 * to the pool." Nothing here deletes a user or an order row.
 */
class DeactivateStaffMember
{
    public function __construct(
        private readonly StaffAuditLogger $audit,
    ) {}

    public function handle(User $actor, User $member): User
    {
        return DB::transaction(function () use ($actor, $member): User {
            $member = User::query()->lockForUpdate()->findOrFail($member->id);

            if ($member->isDeactivated()) {
                return $member;
            }

            $openOrders = Order::query()
                ->where('restaurant_id', $member->restaurant_id)
                ->where('assigned_user_id', $member->id)
                ->whereNotIn('status', [
                    OrderStatus::PAID->value,
                    OrderStatus::CANCELLED->value,
                ])
                ->count();

            if ($openOrders > 0) {
                throw ValidationException::withMessages([
                    'member' => trans_choice(
                        '{1} :name still has :count open order. Reassign it first.|[2,*] :name still has :count open orders. Reassign them first.',
                        $openOrders,
                        ['name' => $member->name, 'count' => $openOrders],
                    ),
                ]);
            }

            $now = now();

            // Release every table assignment back to the pool.
            // Past shifts keep their assignments as history.
            $openShiftIds = Shift::query()
                ->where('user_id', $member->id)
                ->where('ends_at', '>', $now)
                ->pluck('id');

            $releasedTables = DB::table('table_user')
                ->join('tables', 'tables.id', '=', 'table_user.table_id')
                ->whereIn('table_user.shift_id', $openShiftIds)
                ->pluck('tables.number')
                ->map(fn ($number): string => (string) $number)
                ->unique()
                ->values()
                ->all();

            DB::table('table_user')
                ->whereIn('shift_id', $openShiftIds)
                ->delete();

            // Close the live shift now and drop any that had not started.
            Shift::query()
                ->where('user_id', $member->id)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>', $now)
                ->update(['ends_at' => $now]);

            Shift::query()
                ->where('user_id', $member->id)
                ->where('starts_at', '>', $now)
                ->delete();

            // Block sign-in. Existing sessions are ended by
            // EnsureAccountIsActive on their next request.
            $member->forceFill([
                'is_active' => false,
                'deactivated_at' => $now,
                'deactivated_by' => $actor->id,
                'remember_token' => null,
            ])->save();

            $this->audit->record($actor, $member, StaffAudit::DEACTIVATED, [
                'released_tables' => $releasedTables,
            ]);

            return $member;
        });
    }
}
