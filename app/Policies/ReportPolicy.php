<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::FINANCIAL_REPORTS->value);
    }

    public function export(User $user): bool
    {
        return $user->can(Capability::FINANCIAL_REPORTS->value);
    }
}
