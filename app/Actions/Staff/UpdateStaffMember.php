<?php

namespace App\Actions\Staff;

use App\Models\StaffAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateStaffMember
{
    public function __construct(
        private readonly AssignTonightsTables $assignTables,
        private readonly StaffAuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, role: string, tables: list<string>}  $data
     */
    public function handle(User $actor, User $member, array $data): User
    {
        return DB::transaction(function () use ($actor, $member, $data): User {
            $before = [
                'name' => $member->name,
                'role' => $member->getRoleNames()->first(),
                'tables' => $this->currentTables($member),
            ];

            $member->forceFill([
                'name' => $data['name'],
                'job_title' => Str::title($data['role']),
            ])->save();

            $member->syncRoles([$data['role']]);

            $after = [
                'name' => $data['name'],
                'role' => $data['role'],
                'tables' => $this->assignTables->handle($member, $data['tables']),
            ];

            $changes = [];

            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = ['from' => $before[$field], 'to' => $value];
                }
            }

            if ($changes !== []) {
                $this->audit->record($actor, $member, StaffAudit::UPDATED, $changes);
            }

            return $member;
        });
    }

    /**
     * @return list<string>
     */
    private function currentTables(User $member): array
    {
        $now = now();

        return array_values(DB::table('table_user')
            ->join('shifts', 'shifts.id', '=', 'table_user.shift_id')
            ->join('tables', 'tables.id', '=', 'table_user.table_id')
            ->where('table_user.user_id', $member->id)
            ->where('shifts.starts_at', '<=', $now)
            ->where('shifts.ends_at', '>=', $now)
            ->orderBy('tables.sort_order')
            ->orderBy('tables.number')
            ->pluck('tables.number')
            ->map(fn ($number): string => (string) $number)
            ->all());
    }
}
