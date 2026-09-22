<?php

namespace App\Console\Commands;

use App\Services\AdminPermissionInstaller;
use App\Support\AdminAccess;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AdminPermissionsInstall extends Command
{
    protected $signature = 'admin:permissions:install';

    protected $description = 'Idempotently install/backfill the staff permission catalog and starter roles. '
        . 'Safe to re-run on every deploy — never overwrites a role that has already been edited.';

    public function handle(AdminPermissionInstaller $installer): int
    {
        $installer->install();

        $catalogCount = count(AdminAccess::catalog());
        $starterRoles = Role::where('guard_name', 'web')
            ->where('name', 'like', 'staff:%')
            ->where('name', '!=', AdminAccess::LEGACY)
            ->count();

        $this->info("✓ Permission catalog installed ({$catalogCount} permissions).");
        $this->line("  Starter staff roles present: {$starterRoles}");
        $this->line('  Use `php artisan admin:staff:list` to see current roles and assignments.');

        return self::SUCCESS;
    }
}
