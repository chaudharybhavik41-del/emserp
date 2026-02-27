<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CrmQuotationBreakupTemplatePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions so Spatie sees new ones
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate([
            'name'       => 'crm.quotation.breakup_templates.manage',
            'guard_name' => 'web',
        ]);

        // Grant by default to admin roles if they exist
        foreach (['super-admin', 'admin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
