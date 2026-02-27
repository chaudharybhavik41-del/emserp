<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MachineMaintenancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Maintenance Plans
            'machinery.maintenance_plan.view',
            'machinery.maintenance_plan.create',
            'machinery.maintenance_plan.update',
            'machinery.maintenance_plan.delete',
            
            // Maintenance Logs
            'machinery.maintenance_log.view',
            'machinery.maintenance_log.create',
            'machinery.maintenance_log.update',
            'machinery.maintenance_log.complete',
            
            // Breakdown Register
            'machinery.breakdown.view',
            'machinery.breakdown.create',
            'machinery.breakdown.acknowledge',
            'machinery.breakdown.resolve',
            
            // Reports
            'machinery.maintenance.reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // Assign to Admin role
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        // Assign to Maintenance Manager role if exists
        $maintenanceManager = Role::where('name', 'Maintenance Manager')->first();
        if ($maintenanceManager) {
            $maintenanceManager->givePermissionTo([
                'machinery.maintenance_plan.view',
                'machinery.maintenance_plan.create',
                'machinery.maintenance_plan.update',
                'machinery.maintenance_log.view',
                'machinery.maintenance_log.create',
                'machinery.maintenance_log.update',
                'machinery.maintenance_log.complete',
                'machinery.breakdown.view',
                'machinery.breakdown.acknowledge',
                'machinery.breakdown.resolve',
                'machinery.maintenance.reports',
            ]);
        }

        $this->command->info('Machine Maintenance permissions created!');
    }
}
