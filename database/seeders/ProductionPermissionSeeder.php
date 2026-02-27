<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $entities = [
            'production.activity',
            'production.plan',
            'production.dpr',
            'production.qc',
            'production.billing',
            'production.dispatch',
            'production.report',
            'production.dpr.backdate',
            'production.audit.view',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        $perms = [];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                $name = $entity . '.' . $action;
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $perms[] = $name;
            }
        }

        $special = [
            'production.plan.approve',
            'production.dpr.submit',
            'production.dpr.approve',
            'production.qc.perform',
            'production.geofence.override',
            'production.billing.generate',
        ];

        foreach ($special as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $perms[] = $name;
        }

        // Assign to roles (do not remove existing permissions)
        $super = Role::where('name', 'super-admin')->first();
        if ($super) {
            $super->givePermissionTo($perms);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($perms);
        }

        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $mgrPerms = array_filter($perms, function (string $p) {
                if (str_ends_with($p, '.view')) return true;
                if (str_ends_with($p, '.create')) return true;
                if (str_ends_with($p, '.update')) return true;
                if (str_contains($p, '.approve')) return true;
                if (str_contains($p, '.submit')) return true;
                if (str_contains($p, 'production.billing.generate')) return true;
                if (str_contains($p, 'production.qc.perform')) return true;
                return false;
            });

            $manager->givePermissionTo($mgrPerms);
        }
    }
}
