<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * User Role Service
 *
 * Centralizes role resolution and repair logic so the rest of the app does not
 * need to know about the dual role-storage system (user_type column + Spatie
 * roles). The `user_type` column is treated as the canonical source of truth;
 * Spatie roles are kept in sync as a cache/repair layer.
 */
class UserRoleService
{
    /** Known standalone roles in descending privilege order. */
    public const ROLES = ['admin', 'politician', 'citizen', 'voter'];

    /** Role → named dashboard route. */
    public const ROLE_DASHBOARD_ROUTES = [
        'admin'      => 'admin.dashboard',
        'politician' => 'politician.dashboard',
        'citizen'    => 'citizen.dashboard',
        'voter'      => 'voter.dashboard',
    ];

    /**
     * Return the user's canonical primary role based on `user_type`.
     * Falls back to the first matching Spatie role if `user_type` is missing.
     */
    public function resolvePrimaryRole(User $user): ?string
    {
        $userType = $user->user_type;

        if (is_string($userType) && in_array($userType, self::ROLES, true)) {
            return $userType;
        }

        foreach (self::ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Check whether the user has a given role.
     * Prefer `user_type`, then fall back to Spatie roles.
     */
    public function hasRole(User $user, string $role): bool
    {
        if (! in_array($role, self::ROLES, true)) {
            return $user->hasRole($role);
        }

        if ($user->user_type === $role) {
            return true;
        }

        return $user->hasRole($role);
    }

    /**
     * Ensure the Spatie role matching `user_type` is assigned to the user.
     * Creates the role if it does not exist. Returns true when a change was made.
     */
    public function repairSpatieRole(User $user): bool
    {
        $role = $this->resolvePrimaryRole($user);

        if ($role === null) {
            return false;
        }

        if ($user->hasRole($role)) {
            return false;
        }

        try {
            Role::findOrCreate($role, config('auth.defaults.guard', 'web'));
            $user->assignRole($role);

            Log::warning('UserRoleService: repaired missing Spatie role', [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'role'      => $role,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('UserRoleService: failed to repair missing role', [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'role'      => $role,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Return the dashboard route name/URL for a user based on their role.
     */
    public function dashboardRouteFor(User $user): string
    {
        // Dual-role portal picker: voter + citizen → explicit choice.
        if ($this->hasRole($user, 'voter') && $this->hasRole($user, 'citizen')) {
            return route('portal-pick');
        }

        $role = $this->resolvePrimaryRole($user);

        if ($role !== null && array_key_exists($role, self::ROLE_DASHBOARD_ROUTES)) {
            return route(self::ROLE_DASHBOARD_ROUTES[$role]);
        }

        return route('voter.dashboard');
    }

    /**
     * Return the dashboard route name (not URL) for a user.
     */
    public function dashboardRouteNameFor(User $user): string
    {
        $role = $this->resolvePrimaryRole($user);

        if ($role !== null && array_key_exists($role, self::ROLE_DASHBOARD_ROUTES)) {
            return self::ROLE_DASHBOARD_ROUTES[$role];
        }

        return 'voter.dashboard';
    }
}
