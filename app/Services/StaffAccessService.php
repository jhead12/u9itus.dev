<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StaffAccessService
{
    public function assignContributor(User $actor, User $target, bool $enabled): void
    {
        $this->mutate($actor, function () use ($target, $actor, $enabled) {
            $target = User::lockForUpdate()->findOrFail($target->id);
            if ($enabled && ($target->suspended_at || ! $target->email_verified_at || $target->is_guest)) {
                throw ValidationException::withMessages(['contributor' => 'Choose a verified, active, non-guest account.']);
            }
            $role = Role::findOrCreate(\App\Support\ChatterContributorAccess::ROLE, 'web');
            $before = $target->hasRole($role);
            $enabled ? $target->assignRole($role) : $target->removeRole($role);
            if ($enabled && $target->chatter_contributor_requested_at) {
                $target->forceFill(['chatter_contributor_requested_at' => null])->save();
            }
            $this->audit($actor->id, 'contributor.assigned', 'user:'.$target->id,
                ['contributor' => $before], ['contributor' => $enabled]);
        });
    }

    public function dismissContributorRequest(User $actor, User $target): void
    {
        $this->mutate($actor, function () use ($target, $actor) {
            $target = User::lockForUpdate()->findOrFail($target->id);
            $before = $target->chatter_contributor_requested_at;
            $target->forceFill(['chatter_contributor_requested_at' => null])->save();
            $this->audit($actor->id, 'contributor.request.dismissed', 'user:'.$target->id,
                ['requested_at' => $before?->toIso8601String()], ['requested_at' => null]);
        });
    }

    public function mutate(User $actor, callable $callback): mixed
    {
        return DB::transaction(function () use ($actor, $callback) {
            // Serialize owner/role changes across requests, including demotions.
            Role::where('name', AdminAccess::OWNER)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            abort_unless(AdminAccess::owner($actor->fresh()), 403);
            $result = $callback();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            return $result;
        });
    }

    public function saveRole(User $actor, string $label, array $permissions, ?Role $role = null): Role
    {
        return $this->mutate($actor, function () use ($actor, $label, $permissions, $role) {
            if ($role) {
                abort_unless($role->guard_name === 'web' && str_starts_with($role->name, 'staff:') && $role->name !== AdminAccess::LEGACY, 403);
            }
            if (array_diff($permissions, array_keys(AdminAccess::catalog()))) {
                throw ValidationException::withMessages(['permissions' => 'Unknown permission.']);
            }
            $name = 'staff:'.trim($label);
            if ($name === AdminAccess::LEGACY || Role::where('name', $name)->where('guard_name', 'web')->when($role, fn ($q) => $q->where('id', '!=', $role->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'This role name is reserved or already exists.']);
            }
            $before = $role ? ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()] : [];
            $role ??= new Role(['guard_name' => 'web']);
            $role->name = $name;
            $role->save();
            $role->syncPermissions($permissions);
            $this->audit($actor->id, 'role.saved', 'role:'.$role->id, $before, ['name' => $name, 'permissions' => $permissions]);
            return $role;
        });
    }

    public function assign(User $actor, User $target, array $roleIds, bool $owner): void
    {
        $this->mutate($actor, function () use ($actor, $target, $roleIds, $owner) {
            $target = User::lockForUpdate()->findOrFail($target->id);
            if ($target->suspended_at || ! $target->email_verified_at) {
                throw ValidationException::withMessages(['user' => 'Staff must have a verified, active account.']);
            }
            $roles = Role::where('guard_name', 'web')->where('name', 'like', 'staff:%')->whereIn('id', $roleIds)->get();
            if ($roles->count() !== count(array_unique($roleIds))) {
                throw ValidationException::withMessages(['roles' => 'Choose supported staff roles.']);
            }
            if ($target->hasRole(AdminAccess::OWNER) && ! $owner) {
                $otherOwner = User::role(AdminAccess::OWNER, 'web')->where('id', '!=', $target->id)
                    ->whereNull('suspended_at')->whereNotNull('email_verified_at')->whereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'web'))->exists();
                if (! $otherOwner) {
                    throw ValidationException::withMessages(['super_admin' => 'The last active Super Admin cannot be demoted.']);
                }
            }
            $before = ['roles' => $target->getRoleNames()->all(), 'direct_permissions' => $target->getDirectPermissions()->pluck('name')->all()];
            $retained = $target->roles->filter(fn ($r) => ! str_starts_with($r->name, 'staff:') && ! in_array($r->name, ['admin', AdminAccess::OWNER]))->pluck('name')->all();
            $names = array_merge($retained, ['admin'], $roles->pluck('name')->all(), $owner ? [AdminAccess::OWNER] : []);
            $target->syncRoles($names);
            $target->syncPermissions([]);
            $target->forceFill(['user_type' => 'admin'])->save();
            $this->audit($actor->id, 'staff.assigned', 'user:'.$target->id, $before, ['roles' => $names, 'direct_permissions' => []]);
        });
    }

    public function deleteRole(User $actor, Role $role): void
    {
        $this->mutate($actor, function () use ($actor, $role) {
            $role = Role::findOrFail($role->id);
            abort_unless($role->guard_name === 'web' && str_starts_with($role->name, 'staff:') && $role->name !== AdminAccess::LEGACY, 403);
            if ($role->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'Remove this role from its members before deleting it.']);
            }
            $this->audit($actor->id, 'role.deleted', 'role:'.$role->id, ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()], []);
            $role->delete();
        });
    }

    public function audit(?int $actor, string $action, string $target, array $before, array $after): void
    {
        DB::table('staff_access_audits')->insert([
            'actor_id' => $actor, 'action' => $action, 'target' => $target,
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
