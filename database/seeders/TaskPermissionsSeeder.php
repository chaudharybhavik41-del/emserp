<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TaskPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define task-related permissions
        $permissions = [
            // Task permissions
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.bulk_update',

            // Task list permissions
            'tasks.list.view',
            'tasks.list.create',
            'tasks.list.update',
            'tasks.list.delete',

            // Task settings permissions (statuses, priorities, labels, templates)
            'tasks.settings.view',
            'tasks.settings.manage',

            // Time tracking
            'tasks.time.view',
            'tasks.time.log',
            'tasks.time.manage',

            // Reports
            'tasks.reports.view',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Assign permissions to roles
        $superAdmin = Role::findByName('super-admin', 'web');
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        $admin = Role::findByName('admin', 'web');
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        // Manager role - can do everything except delete and manage settings
        $manager = Role::findByName('manager', 'web');
        if ($manager) {
            $managerPerms = array_filter($permissions, function ($perm) {
                return !str_contains($perm, '.delete') && !str_contains($perm, '.settings.manage');
            });
            $manager->givePermissionTo($managerPerms);
        }

        // Operator role - basic task operations
        $operator = Role::findByName('operator', 'web');
        if ($operator) {
            $operatorPerms = [
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.list.view',
                'tasks.time.view',
                'tasks.time.log',
            ];
            $operator->givePermissionTo($operatorPerms);
        }

        // Viewer role - view only
        $viewer = Role::findByName('viewer', 'web');
        if ($viewer) {
            $viewerPerms = array_filter($permissions, fn($p) => str_contains($p, '.view'));
            $viewer->givePermissionTo($viewerPerms);
        }

        // Clear cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
