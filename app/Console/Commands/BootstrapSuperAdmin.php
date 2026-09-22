<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminPermissionInstaller;
use App\Services\StaffAccessService;
use App\Support\AdminAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'admin:bootstrap-owner {email : Existing verified account to promote}';
    protected $description = 'Explicitly promote an existing active account to Super Admin; audited, no email or password change';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || $user->suspended_at || ! $user->email_verified_at) {
            $this->error('Choose an existing, verified, non-suspended account.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($user) {
            app(AdminPermissionInstaller::class)->install();
            Role::where('name', AdminAccess::OWNER)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $before = $user->getRoleNames()->all();
            $user->assignRole('admin', AdminAccess::OWNER);
            $user->forceFill(['user_type' => 'admin'])->save();
            app(StaffAccessService::class)->audit(null, 'owner.bootstrap.cli', 'user:'.$user->id, ['roles' => $before], ['roles' => $user->getRoleNames()->all()]);
        });
        $this->info('Super Admin access granted to account '.$user->id.'. Existing authentication and 2FA still apply.');
        return self::SUCCESS;
    }
}
