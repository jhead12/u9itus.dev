<?php

namespace App\Services;

use App\Models\User;
use App\Models\WixSite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bridges Wix instance identity with Laravel auth.
 *
 * When a request arrives from a Wix iframe, the decoded instance payload
 * contains the member's identity (memberId, email, etc.). This service
 * resolves or creates a Laravel User and logs them in via the session.
 *
 * Typical Wix instance payload:
 * {
 *   "instanceId": "abc-123",
 *   "appDefId": "...",
 *   "signDate": "2026-02-16T...",
 *   "uid": "member-uuid",        // Wix member UID (present when member logged in)
 *   "permissions": "OWNER",
 *   "ipAndPort": "1.2.3.4:5678",
 *   "vendorProductId": null,
 *   "aid": "...",                 // anonymous visitor ID
 *   "siteOwnerId": "..."
 * }
 *
 * Note: `uid` is only present for site members/owners, not anonymous visitors.
 */
class WixSsoService
{
    /**
     * Attempt to resolve or create a Laravel user from the decoded Wix instance.
     * Returns null if the visitor is anonymous (no member identity).
     */
    public function resolveUser(array $decodedInstance): ?User
    {
        $memberId   = $decodedInstance['uid'] ?? null;
        $instanceId = $decodedInstance['instanceId'] ?? null;

        // Anonymous visitor — no SSO possible
        if (empty($memberId)) {
            return null;
        }

        // 1. Try to find existing user by wix_member_id
        $user = User::where('wix_member_id', $memberId)->first();

        if ($user) {
            // Keep the instance_id up to date
            if ($user->wix_instance_id !== $instanceId) {
                $user->update(['wix_instance_id' => $instanceId]);
            }
            return $user;
        }

        // 2. Try to find by matching Wix site owner email
        $site = $instanceId ? WixSite::where('instance_id', $instanceId)->first() : null;
        $ownerEmail = $site?->owner_email;

        if ($ownerEmail) {
            $user = User::where('email', $ownerEmail)->first();
            if ($user) {
                $user->update([
                    'wix_member_id'   => $memberId,
                    'wix_instance_id' => $instanceId,
                ]);
                Log::info("Wix SSO: linked existing user {$user->id} to Wix member {$memberId}");
                return $user;
            }
        }

        // 3. Create a new user for this Wix member
        $user = User::create([
            'name'            => $this->buildDisplayName($decodedInstance),
            'email'           => $ownerEmail, // may be null — profile update needed later
            'password'        => null,         // SSO user — no password
            'user_type'       => $this->inferUserType($decodedInstance),
            'wix_member_id'   => $memberId,
            'wix_instance_id' => $instanceId,
        ]);

        Log::info("Wix SSO: created new user {$user->id} for Wix member {$memberId}");

        return $user;
    }

    /**
     * Resolve the user and log them into the Laravel session.
     * Returns the logged-in user, or null if anonymous.
     */
    public function loginFromInstance(array $decodedInstance): ?User
    {
        $user = $this->resolveUser($decodedInstance);

        if ($user) {
            Auth::login($user);
        }

        return $user;
    }

    /**
     * Build a display name from instance data.
     */
    protected function buildDisplayName(array $instance): string
    {
        // Wix doesn't send the name in the instance payload itself;
        // default to a placeholder — the user can update via profile later.
        $permissions = $instance['permissions'] ?? '';

        if ($permissions === 'OWNER') {
            return 'Site Owner';
        }

        return 'Wix Member';
    }

    /**
     * Infer a user_type based on Wix permissions.
     * Site owners/admins are treated as advertisers (politicians);
     * regular members are viewers (voters).
     */
    protected function inferUserType(array $instance): string
    {
        $permissions = $instance['permissions'] ?? '';

        return in_array($permissions, ['OWNER', 'ADMIN'], true)
            ? 'advertiser'
            : 'viewer';
    }
}
