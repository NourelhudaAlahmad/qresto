<?php

namespace App\Actions\Staff;

use App\Models\StaffAudit;
use App\Models\User;

class StaffAuditLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(
        User $actor,
        User $subject,
        string $action,
        array $changes = [],
    ): StaffAudit {
        return StaffAudit::query()->create([
            'restaurant_id' => $subject->restaurant_id,
            'actor_id' => $actor->id,
            'user_id' => $subject->id,
            'action' => $action,
            'changes' => $changes === [] ? null : $changes,
            'created_at' => now(),
        ]);
    }
}
