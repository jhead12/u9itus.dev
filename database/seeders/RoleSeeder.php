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
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $advertiserRole = Role::firstOrCreate(['name' => 'advertiser']);
        $viewerRole = Role::firstOrCreate(['name' => 'viewer']);

        // Assign permissions to roles (use sync to avoid duplicates)
        $adminRole->syncPermissions([$manageAssignments->name, $viewReports->name]);
        $advertiserRole->syncPermissions([$manageCampaigns->name, $viewReports->name]);
        $viewerRole->syncPermissions([$watchAds->name]);
    }
}
