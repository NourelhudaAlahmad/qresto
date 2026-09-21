<?php

namespace App\Services;

use App\Models\User;

class CapabilitySnapshot
{
    public const SESSION_KEY = 'staff_capabilities';

    /**
     * Create and store the user's current capabilities in the session.
     *
     * @return array<int, string>
     */
    public function store(User $user): array
    {
        $capabilities = $user->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();

        session()->put(self::SESSION_KEY, $capabilities);

        return $capabilities;
    }

    /**
     * Get the capability snapshot currently stored in the session.
     *
     * @return array<int, string>
     */
    public function get(User $user): array
    {
        $capabilities = session()->get(self::SESSION_KEY);

        if (! is_array($capabilities)) {
            return $this->store($user);
        }

        return array_values(
            array_filter(
                $capabilities,
                static fn (mixed $capability): bool => is_string($capability),
            ),
        );
    }

    /**
     * Forget the current capability snapshot.
     */
    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
