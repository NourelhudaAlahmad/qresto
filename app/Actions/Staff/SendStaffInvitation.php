<?php

namespace App\Actions\Staff;

use App\Models\StaffAudit;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;

class SendStaffInvitation
{
    public function __construct(
        private readonly StaffAuditLogger $audit,
    ) {}

    public function handle(User $actor, User $member): void
    {
        $broker = Password::broker('staff_invites');

        $token = $broker->createToken($member);

        $member->notify(new StaffInvitation(
            token: $token,
            expiresInMinutes: (int) config('auth.passwords.staff_invites.expire'),
            invitedBy: $actor->name,
        ));

        $this->audit->record($actor, $member, StaffAudit::INVITED);
    }
}
