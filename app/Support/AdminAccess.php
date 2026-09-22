<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    public const OWNER = 'super_admin';
    public const LEGACY = 'staff:Legacy administrator';

    public static function catalog(): array
    {
        $keys = array_merge(array_values(config('admin_routes', [])), [
            'chatter.publish', 'chatter.moderate', 'blog.create', 'blog.edit',
        ]);
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => ! str_starts_with($key, '@'))));
        sort($keys);
        return array_combine($keys, array_map(fn ($key) => ucfirst(str_replace(['.', '_'], ' ', $key)), $keys));
    }

    public static function isStaff(User $user): bool
    {
        return $user->hasRole('admin', 'web') && ! $user->suspended_at && $user->email_verified_at !== null;
    }

    public static function owner(User $user): bool
    {
        return self::isStaff($user) && $user->hasRole(self::OWNER, 'web');
    }

    public static function allowed(User $user, string $permission): bool
    {
        return self::isStaff($user) && isset(self::catalog()[$permission])
            && (self::owner($user) || $user->checkPermissionTo($permission, 'web'));
    }

    public static function routePermission(string $name, ?string $action = null): ?string
    {
        $permission = config('admin_routes', [])[$name] ?? null;
        if ($permission !== '@action') {
            return $permission;
        }
        return match ($name) {
            'admin.politician-chatter.moderate' => match ($action) {
                'publish' => 'chatter.publish',
                'reject', 'archive', 'return_to_review' => 'chatter.moderate',
                default => null,
            },
            'admin.posts.bulk-action' => match ($action) {
                'approve' => 'blog.publish',
                'unpublish', 'archive', 'restore' => 'blog.archive',
                'delete' => 'blog.delete',
                default => null,
            },
            'admin.users.bulk-action' => match ($action) {
                'suspend', 'unsuspend' => 'users.suspend',
                'kyc_approve', 'kyc_reject' => 'kyc.review',
                default => null,
            },
            'admin.campaigns.bulk-action' => match ($action) {
                'approve', 'reject' => 'campaigns.approve',
                'stop', 'reactivate' => 'campaigns.manage',
                default => null,
            },
            'admin.candidate-matches.bulk-action', 'admin.data-quality.bulk-action' => 'civic.edit',
            default => null,
        };
    }

    public static function canRoute(User $user, string $name, ?string $action = null): bool
    {
        if (! self::isStaff($user)) {
            return false;
        }
        $allowed = match ($permission = self::routePermission($name, $action)) {
            '@staff' => true,
            '@owner' => self::owner($user),
            null => false,
            default => self::allowed($user, $permission),
        };
        foreach (config('admin_route_requirements', [])[$name] ?? [] as $extra) {
            $allowed = $allowed && self::allowed($user, $extra);
        }
        return $allowed;
    }
}
