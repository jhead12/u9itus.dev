<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions first (idempotent)
        $manageAssignments = Permission::firstOrCreate(['name' => 'manage assignments']);
        $manageCampaigns = Permission::firstOrCreate(['name' => 'manage campaigns']);
        $watchAds = Permission::firstOrCreate(['name' => 'watch ads']);
        $viewReports = Permission::firstOrCreate(['name' => 'view reports']);

        // Create roles (idempotent)
        $adminRole      = Role::firstOrCreate(['name' => 'admin']);
        $politicianRole = Role::firstOrCreate(['name' => 'politician']);
        $voterRole      = Role::firstOrCreate(['name' => 'voter']);
        $citizenRole    = Role::firstOrCreate(['name' => 'citizen']);
        // Legacy roles kept for backward compatibility
        Role::firstOrCreate(['name' => 'advertiser']);
        Role::firstOrCreate(['name' => 'viewer']);

        // Assign permissions to roles (use sync to avoid duplicates)
        $adminRole->syncPermissions([$manageAssignments->name, $manageCampaigns->name, $viewReports->name]);
        $politicianRole->syncPermissions([$manageCampaigns->name, $viewReports->name]);
        $voterRole->syncPermissions([$watchAds->name]);
        $citizenRole->syncPermissions([$manageCampaigns->name]);
    }
}
