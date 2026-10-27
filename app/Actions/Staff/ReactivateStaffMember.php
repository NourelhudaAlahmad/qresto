<?php

namespace App\Actions\Staff;

use App\Models\StaffAudit;
use App\Models\User;

class ReactivateStaffMember
{
    public function __construct(
        private readonly StaffAuditLogger $audit,
    ) {}

    public function handle(User $actor, User $member): User
    {
        if (! $member->isDeactivated() && $member->is_active) {
            return $member;
        }

        $member->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->audit->record($actor, $member, StaffAudit::REACTIVATED);

        return $member;
    }
}
