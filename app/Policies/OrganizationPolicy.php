<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Support\AdminAccess;

/**
 * Portal ownership — deliberately NOT built on the Super Admin/staff
 * permission system (App\Support\AdminAccess, Spatie roles). That system is
 * global/platform-staff-only by design (see its own docs); an org owner
 * editing only their own portal is a separate, narrower authorization
 * concern, plain ownership-by-user_id with a staff override for support.
 */
class OrganizationPolicy
{
    public function update(User $user, Organization $organization): bool
    {
        return $organization->user_id === $user->id || AdminAccess::isStaff($user);
    }

    public function publish(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}
