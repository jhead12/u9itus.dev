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

        // Create permissions first
        $manageAssignments = Permission::create(['name' => 'manage assignments']);
        $manageCampaigns = Permission::create(['name' => 'manage campaigns']);
        $watchAds = Permission::create(['name' => 'watch ads']);
        $viewReports = Permission::create(['name' => 'view reports']);

        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $advertiserRole = Role::create(['name' => 'advertiser']);
        $viewerRole = Role::create(['name' => 'viewer']);

        // Assign permissions to roles
        $adminRole->givePermissionTo([$manageAssignments, $viewReports]);
        $advertiserRole->givePermissionTo([$manageCampaigns, $viewReports]);
        $viewerRole->givePermissionTo([$watchAds]);
    }
}
