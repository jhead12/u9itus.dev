<?php

namespace App\Support;

use App\Models\User;

final class ChatterContributorAccess
{
    public const ROLE = 'chatter_contributor';

    public static function allowed(User $user): bool
    {
        return ! $user->suspended_at && ! $user->is_guest && $user->email_verified_at !== null
            && ($user->hasRole(self::ROLE, 'web') || AdminAccess::allowed($user, 'chatter.create'));
    }
}
