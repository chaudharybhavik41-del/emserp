<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MachineCalibrationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'machinery.calibration.view',
            'machinery.calibration.create',
            'machinery.calibration.update',
            'machinery.calibration.delete',
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

        // Assign to Quality Manager role if exists
        $qualityManager = Role::where('name', 'Quality Manager')->first();
        if ($qualityManager) {
            $qualityManager->givePermissionTo($permissions);
        }

        $this->command->info('Machine Calibration permissions created!');
    }
}
