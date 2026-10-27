<?php

namespace App\Actions\Staff;

use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tonight's shift and table assignments for one staff member.
 *
 * A table belongs to one person per night, so assigning it here moves it
 * off anyone else's live shift: "add Omar with tables 04 and 12" makes
 * those Omar's tables on the floor.
 */
class AssignTonightsTables
{
    /**
     * @param  list<string>  $tableNumbers
     * @return list<string> the table numbers now assigned, in floor order
     */
    public function handle(User $member, array $tableNumbers): array
    {
        return DB::transaction(function () use ($member, $tableNumbers): array {
            $shift = $this->currentOrNewShift($member);

            $tables = RestaurantTable::query()
                ->where('restaurant_id', $member->restaurant_id)
                ->whereIn('number', $tableNumbers)
                ->orderBy('sort_order')
                ->orderBy('number')
                ->get(['id', 'number']);

            $tableIds = $tables->pluck('id')->all();

            // Release these tables from every other live shift tonight.
            if ($tableIds !== []) {
                DB::table('table_user')
                    ->whereIn('table_id', $tableIds)
                    ->where('shift_id', '!=', $shift->id)
                    ->whereIn('shift_id', $this->liveShiftIds($member))
                    ->delete();
            }

            DB::table('table_user')->where('shift_id', $shift->id)->delete();

            DB::table('table_user')->insert(
                array_map(
                    fn (int $tableId): array => [
                        'shift_id' => $shift->id,
                        'user_id' => $member->id,
                        'table_id' => $tableId,
                    ],
                    $tableIds,
                ),
            );

            return array_values($tables
                ->pluck('number')
                ->map(fn ($number): string => (string) $number)
                ->all());
        });
    }

    private function currentOrNewShift(User $member): Shift
    {
        $now = now();

        $shift = Shift::query()
            ->where('restaurant_id', $member->restaurant_id)
            ->where('user_id', $member->id)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderByDesc('starts_at')
            ->first();

        return $shift ?? Shift::query()->create([
            'restaurant_id' => $member->restaurant_id,
            'user_id' => $member->id,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addHours(
                (int) config('qresto.staff.default_shift_hours', 8),
            ),
        ]);
    }

    /**
     * @return list<int>
     */
    private function liveShiftIds(User $member): array
    {
        $now = now();

        return array_values(Shift::query()
            ->where('restaurant_id', $member->restaurant_id)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }
}
