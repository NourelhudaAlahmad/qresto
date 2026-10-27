<?php

namespace App\Actions\Staff;

use App\Models\StaffAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStaffMember
{
    public function __construct(
        private readonly AssignTonightsTables $assignTables,
        private readonly SendStaffInvitation $invite,
        private readonly StaffAuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, role: string, tables: list<string>, send_invite: bool}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $member = DB::transaction(function () use ($actor, $data): User {
            $member = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                // Unusable until the invitation link sets a real one.
                'password' => Str::password(40),
                'restaurant_id' => $actor->restaurant_id,
                'is_active' => true,
                'initials' => $this->initials($data['name']),
                'job_title' => Str::title($data['role']),
            ]);

            $member->syncRoles([$data['role']]);

            $tables = $this->assignTables->handle($member, $data['tables']);

            $this->audit->record($actor, $member, StaffAudit::CREATED, [
                'role' => $data['role'],
                'tables' => $tables,
            ]);

            return $member;
        });

        // Outside the transaction: the mail is queued only for a saved user.
        if ($data['send_invite']) {
            $this->invite->handle($actor, $member);
        }

        return $member;
    }

    private function initials(string $name): string
    {
        return Str::upper(
            collect(preg_split('/\s+/', trim($name)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => Str::substr($part, 0, 1))
                ->implode(''),
        );
    }
}
