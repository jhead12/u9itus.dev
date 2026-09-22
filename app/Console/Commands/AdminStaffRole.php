<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffAccessService;
use App\Support\AdminAccess;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminStaffRole extends Command
{
    protected $signature = 'admin:staff:role
                            {name : Role label, e.g. "Social Reviewer" (without the staff: prefix)}
                            {--permission=* : Permission key to grant; repeat for multiple. Ignored with --delete}
                            {--delete : Delete this role instead of creating/updating it}
                            {--actor= : Email of the Super Admin performing this change (required)}';

    protected $description = 'Create, update, or delete a delegated staff role from the CLI. '
        . 'Goes through the same StaffAccessService used by the staff-access admin page, '
        . 'so authorization, validation, and the audit log all behave identically.';

    public function handle(StaffAccessService $service): int
    {
        $actor = $this->resolveActor();
        if (! $actor) {
            return self::FAILURE;
        }

        $label = trim($this->argument('name'));
        $roleName = 'staff:'.$label;
        $existing = Role::where('guard_name', 'web')->where('name', $roleName)->first();

        try {
            if ($this->option('delete')) {
                if (! $existing) {
                    $this->error("✗ No staff role named \"{$label}\" exists.");
                    return self::FAILURE;
                }
                $service->deleteRole($actor, $existing);
                $this->info("✓ Deleted role \"{$label}\".");
                return self::SUCCESS;
            }

            $permissions = $this->option('permission');
            $role = $service->saveRole($actor, $label, $permissions, $existing);

            $this->info(($existing ? '✓ Updated' : '✓ Created')." role \"{$label}\".");
            $this->line('  Permissions: '.($role->permissions->pluck('name')->implode(', ') ?: '(none)'));

            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error("✗ {$message}");
                }
            }
            if ($this->option('permission') && ! $this->option('delete')) {
                $unknown = array_diff($this->option('permission'), array_keys(AdminAccess::catalog()));
                if ($unknown) {
                    $this->line('  Unknown permission(s): '.implode(', ', $unknown));
                    $this->line('  Run `php artisan admin:staff:list` to see the full permission catalog.');
                }
            }
            return self::FAILURE;
        } catch (HttpException $e) {
            $this->error("✗ Not permitted: {$actor->email} is not a Super Admin.");
            return self::FAILURE;
        }
    }

    private function resolveActor(): ?User
    {
        $email = $this->option('actor');
        if (! $email) {
            $this->error('✗ --actor=<email> is required. The action is authorized and audited as that Super Admin.');
            return null;
        }
        $actor = User::where('email', $email)->first();
        if (! $actor) {
            $this->error("✗ No user found with email: {$email}");
            return null;
        }
        if (! AdminAccess::owner($actor)) {
            $this->error("✗ {$email} is not a Super Admin. Only a Super Admin can manage staff roles.");
            return null;
        }
        return $actor;
    }
}
