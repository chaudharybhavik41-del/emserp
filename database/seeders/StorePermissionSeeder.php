<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StorePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions so Spatie sees new ones
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
         * STORE MODULE PERMISSIONS
         */

        // 1) GRN / Material Receipt permissions
        $viewGrn = Permission::firstOrCreate(
            ['name' => 'store.material_receipt.view', 'guard_name' => 'web']
        );
        $createGrn = Permission::firstOrCreate(
            ['name' => 'store.material_receipt.create', 'guard_name' => 'web']
        );
        $updateGrn = Permission::firstOrCreate(
            ['name' => 'store.material_receipt.update', 'guard_name' => 'web']
        );
        $deleteGrn = Permission::firstOrCreate(
            ['name' => 'store.material_receipt.delete', 'guard_name' => 'web']
        );

        // 2) Store stock permissions
        $viewStock = Permission::firstOrCreate(
            ['name' => 'store.stock.view', 'guard_name' => 'web']
        );
        $updateStock = Permission::firstOrCreate(
            ['name' => 'store.stock.update', 'guard_name' => 'web']
        );

        // 3) Store requisition permissions
        $viewRequisition = Permission::firstOrCreate(
            ['name' => 'store.requisition.view', 'guard_name' => 'web']
        );
        $createRequisition = Permission::firstOrCreate(
            ['name' => 'store.requisition.create', 'guard_name' => 'web']
        );

        // 4) Store issue permissions
        $viewIssue = Permission::firstOrCreate(
            ['name' => 'store.issue.view', 'guard_name' => 'web']
        );
        $createIssue = Permission::firstOrCreate(
            ['name' => 'store.issue.create', 'guard_name' => 'web']
        );

        // Post store issue to accounts (DEV-2)
        $postIssueToAccounts = Permission::firstOrCreate(
            ['name' => 'store.issue.post_to_accounts', 'guard_name' => 'web']
        );

                // 5) Gate pass permissions
        $viewGatepass = Permission::firstOrCreate(
            ['name' => 'store.gatepass.view', 'guard_name' => 'web']
        );
        $createGatepass = Permission::firstOrCreate(
            ['name' => 'store.gatepass.create', 'guard_name' => 'web']
        );

        // 6) Store return permissions
        $viewReturn = Permission::firstOrCreate(
            ['name' => 'store.return.view', 'guard_name' => 'web']
        );
        $createReturn = Permission::firstOrCreate(
            ['name' => 'store.return.create', 'guard_name' => 'web']
        );

        // Collect all store permissions
        $allPerms = [
            $viewGrn,
            $createGrn,
            $updateGrn,
            $deleteGrn,

            $viewStock,
            $updateStock,

            $viewRequisition,
            $createRequisition,

            $viewIssue,
            $createIssue,
            $postIssueToAccounts,

            $viewGatepass,
            $createGatepass,

            $viewReturn,
            $createReturn,
        ];

        // Helper: give ALL store permissions to a role if it exists
        $grantAllTo = function (string $roleName) use ($allPerms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($allPerms);
            }
        };

        // Helper: give a SUBSET of permissions to a role if it exists
        $grantSomeTo = function (string $roleName, array $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        };

        /*
         * Full store access
         */
        $grantAllTo('super-admin');
        $grantAllTo('admin');
        $grantAllTo('Admin');
        $grantAllTo('Store');
        $grantAllTo('store');
        $grantAllTo('Stores');

        /*
         * Production roles: only requisition
         */
        $grantSomeTo('Production', [
            $viewRequisition,
            $createRequisition,
        ]);
        $grantSomeTo('production', [
            $viewRequisition,
            $createRequisition,
        ]);
        $grantSomeTo('ProductionSupervisor', [
            $viewRequisition,
            $createRequisition,
        ]);

        /*
         * Viewer role: read-only access
         */
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewOnly = [
                $viewGrn,
                $viewStock,
                $viewRequisition,
                $viewIssue,
                $viewGatepass,
                $viewReturn,
            ];
            $viewer->givePermissionTo($viewOnly);
        }

        // Clear again at the end
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}


