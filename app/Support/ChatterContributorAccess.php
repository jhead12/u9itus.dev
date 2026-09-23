<?php

namespace App\Support;

use App\Models\User;

final class ChatterContributorAccess
{
    public const ROLE = 'chatter_contributor';

    public static function allowed(User $user): bool
    {
        return ! $user->suspended_at && ! $user->is_guest && self::isVerified($user)
            && ($user->hasRole(self::ROLE, 'web') || AdminAccess::allowed($user, 'chatter.create'));
    }

    /**
     * "Verified" here means the account owner has proven they're a real,
     * reachable person by at least one channel — most voters on this
     * platform verify by phone during onboarding and never touch email
     * verification, so requiring email_verified_at alone would block
     * nearly every organic contributor request.
     */
    public static function isVerified(User $user): bool
    {
        return $user->email_verified_at !== null || $user->phone_verified_at !== null;
    }
}
