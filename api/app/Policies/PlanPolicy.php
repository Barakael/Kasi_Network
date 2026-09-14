<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plan $plan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->managesTenant();
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->managesTenant();
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->managesTenant();
    }
}
