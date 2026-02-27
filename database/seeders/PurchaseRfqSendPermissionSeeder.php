<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchaseRfqSendPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $view = Permission::firstOrCreate(
            ['name' => 'purchase.rfq.view', 'guard_name' => 'web']
        );
        $create = Permission::firstOrCreate(
            ['name' => 'purchase.rfq.create', 'guard_name' => 'web']
        );

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($view);
        $admin->givePermissionTo($create);

        $purchaseRole = Role::where('name', 'Purchase')->first();
        if ($purchaseRole) {
            $purchaseRole->givePermissionTo($view);
            $purchaseRole->givePermissionTo($create);
        }
    }
}
