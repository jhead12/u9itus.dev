<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AdminStaffList extends Command
{
    protected $signature = 'admin:staff:list
                            {--role= : Show only this staff role (label, without the staff: prefix)}
                            {--catalog : Also print the full available permission catalog}';

    protected $description = 'List staff roles, their permissions, and which admin accounts hold each one. Read-only.';

    public function handle(): int
    {
        $roles = Role::with('users')->where('guard_name', 'web')
            ->where(fn ($q) => $q->where('name', 'like', 'staff:%')->orWhere('name', AdminAccess::OWNER))
            ->when($this->option('role'), fn ($q, $label) => $q->where('name', 'staff:'.trim($label)))
            ->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->warn('No matching roles found.');
            return self::SUCCESS;
        }

        foreach ($roles as $role) {
            $label = $role->name === AdminAccess::OWNER ? 'Super Admin (owner)' : substr($role->name, 6);
            $this->line("<fg=cyan;options=bold>{$label}</>");
            $permissions = $role->name === AdminAccess::OWNER
                ? 'all (owner bypass)'
                : ($role->permissions->pluck('name')->implode(', ') ?: '(none)');
            $this->line("  Permissions: {$permissions}");
            $members = $role->users->pluck('email')->all();
            $this->line('  Members: '.($members ? implode(', ', $members) : '(none)'));
            $this->newLine();
        }

        $owners = User::role(AdminAccess::OWNER, 'web')->whereNull('suspended_at')->whereNotNull('email_verified_at')->count();
        $this->line("Active Super Admins: {$owners}".($owners <= 1 ? '  (last-owner protection is active)' : ''));

        if ($this->option('catalog')) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>Full permission catalog</>');
            $this->line('  '.implode(', ', array_keys(AdminAccess::catalog())));
        }

        return self::SUCCESS;
    }
}
