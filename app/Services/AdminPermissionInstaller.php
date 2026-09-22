<?php

namespace App\Services;

use App\Support\AdminAccess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionInstaller
{
    public function install(): void
    {
        foreach (array_keys(AdminAccess::catalog()) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate(AdminAccess::OWNER, 'web');
        $templates = [
            'Social Reviewer' => ['chatter.view', 'chatter.create', 'chatter.edit', 'chatter.moderate'],
            'Social Publisher' => ['chatter.view', 'chatter.create', 'chatter.edit', 'chatter.moderate', 'chatter.publish'],
            'Blog Editor' => ['blog.view', 'blog.create', 'blog.edit'],
            'Blog Publisher' => ['blog.view', 'blog.create', 'blog.edit', 'blog.publish', 'blog.archive'],
            'Campaign Reviewer' => ['campaigns.view', 'campaigns.approve'],
            'Finance Viewer' => ['finance.reports.view'],
            'Finance Operator' => ['finance.reports.view', 'finance.export', 'finance.refunds.manage', 'finance.payouts.manage'],
        ];
        foreach ($templates as $label => $permissions) {
            $role = Role::firstOrCreate(['name' => 'staff:'.$label, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
