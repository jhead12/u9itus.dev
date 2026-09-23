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
     * Accept contact verification or the identity-verification methods used
     * by Voter::canViewToday(). This establishes eligibility only: an owner
     * must still grant the contributor role before sources can be submitted.
     */
    public static function isVerified(User $user): bool
    {
        return $user->email_verified_at !== null
            || $user->phone_verified_at !== null
            || $user->idme_verified_at !== null
            || $user->voter()->where('is_active', true)->where('flagged_for_fraud', false)
                ->where(fn ($query) => $query->where('is_verified', true)
                    ->orWhere('stripe_account_status', 'active'))->exists();
    }
}
