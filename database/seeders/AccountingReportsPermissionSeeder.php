<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 3: Accounting Reports permission seeder.
 *
 * Notes:
 * - The sidebar already gates report menu items behind `accounting.reports.view`.
 * - Many report controllers were previously protected by `accounting.vouchers.view`.
 *   In Phase 3 we standardize report access using `accounting.reports.view`.
 *
 * This seeder is intentionally SAFE:
 * - It creates the permission if missing.
 * - It grants it to common admin/view roles WITHOUT syncing (won't wipe existing perms).
 */
class AccountingReportsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'accounting.reports.view',
        ];

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        // Grant to existing roles (do not sync; only add missing)
        $roleNames = ['super-admin', 'admin', 'viewer', 'manager'];
        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            foreach ($permissionNames as $permName) {
                if (! $role->hasPermissionTo($permName)) {
                    $role->givePermissionTo($permName);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
