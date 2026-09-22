<?php

use App\Services\AdminPermissionInstaller;
use App\Support\AdminAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            app(AdminPermissionInstaller::class)->install();
            $legacy = Role::findOrCreate(AdminAccess::LEGACY, 'web');
            $legacy->syncPermissions(array_keys(AdminAccess::catalog()));
            // One-time snapshot: never grant legacy access at login or seeding.
            \App\Models\User::where('user_type', 'admin')->orWhereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'web'))
                ->eachById(function ($user) use ($legacy) {
                    $user->assignRole('admin', $legacy);
                });
        });
    }

    public function down(): void
    {
        // Preserve grants. Recovery must not reopen unrestricted endpoints.
    }
};
