<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NasDevice;
use App\Models\User;

class NasDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesTenant();
    }

    public function view(User $user, NasDevice $device): bool
    {
        return $user->managesTenant();
    }

    public function create(User $user): bool
    {
        return $user->managesTenant();
    }

    public function update(User $user, NasDevice $device): bool
    {
        return $user->managesTenant();
    }

    public function delete(User $user, NasDevice $device): bool
    {
        return $user->managesTenant();
    }

    /**
     * Ending a client's session from the console.
     */
    public function disconnectSessions(User $user, NasDevice $device): bool
    {
        return $user->managesTenant();
    }
}
