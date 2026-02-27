<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchasePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions so Spatie sees new ones
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Core purchase indent permissions
        $viewIndent = Permission::firstOrCreate(
            ['name' => 'purchase.indent.view', 'guard_name' => 'web']
        );
        $createIndent = Permission::firstOrCreate(
            ['name' => 'purchase.indent.create', 'guard_name' => 'web']
        );
        $updateIndent = Permission::firstOrCreate(
            ['name' => 'purchase.indent.update', 'guard_name' => 'web']
        );
        $approveIndent = Permission::firstOrCreate(
            ['name' => 'purchase.indent.approve', 'guard_name' => 'web']
        );

        // RFQ permissions (keep them in same seeder so all purchase perms live together)
        $viewRfq = Permission::firstOrCreate(
            ['name' => 'purchase.rfq.view', 'guard_name' => 'web']
        );
        $createRfq = Permission::firstOrCreate(
            ['name' => 'purchase.rfq.create', 'guard_name' => 'web']
        );
        $sendRfq = Permission::firstOrCreate(
            ['name' => 'purchase.rfq.send', 'guard_name' => 'web']
        );

        $allPerms = [
            $viewIndent,
            $createIndent,
            $updateIndent,
            $approveIndent,
            $viewRfq,
            $createRfq,
            $sendRfq,
        ];

        // Helper: give all purchase permissions to a role if it exists
        $grantAllTo = function (string $roleName) use ($allPerms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($allPerms);
            }
        };

        // Super / admin roles get full purchase permissions
        $grantAllTo('super-admin');
        $grantAllTo('admin');   // lowercase admin
        $grantAllTo('Admin');   // uppercase Admin
        $grantAllTo('Purchase'); // dedicated purchase role if you create it

        // Viewer: only *.view permissions
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewOnly = [
                $viewIndent,
                $viewRfq,
            ];
            $viewer->givePermissionTo($viewOnly);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
