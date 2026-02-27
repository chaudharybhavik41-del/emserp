<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RaBillsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions so new permissions are recognized immediately
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Subcontractor RA Bills
            'subcontractor_ra.view',
            'subcontractor_ra.create',
            'subcontractor_ra.update',
            'subcontractor_ra.delete',
            'subcontractor_ra.submit',
            'subcontractor_ra.approve',
            'subcontractor_ra.reject',
            'subcontractor_ra.post',

            // Client RA Bills
            'client_ra.view',
            'client_ra.create',
            'client_ra.update',
            'client_ra.delete',
            'client_ra.submit',
            'client_ra.approve',
            'client_ra.reject',
            'client_ra.post',
        ];

        // Create permissions if missing
        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        // Assign: admin / super-admin / manager => all RA permissions
        $fullRoles = ['super-admin', 'admin', 'manager'];
        foreach ($fullRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            foreach ($permissions as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        // Viewer => view only
        $viewer = Role::where('name', 'viewer')->where('guard_name', 'web')->first();
        if ($viewer) {
            $viewOnly = array_filter($permissions, fn ($p) => str_ends_with($p, '.view'));
            foreach ($viewOnly as $perm) {
                if (! $viewer->hasPermissionTo($perm)) {
                    $viewer->givePermissionTo($perm);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
