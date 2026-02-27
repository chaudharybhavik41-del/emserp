<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // List of all entities we want permissions for
        $entities = [
            // Core module
            'core.user',
            'core.role',
            'core.department',
            'core.uom',
            'core.company',
            'core.system_setting',
            'core.mail_profile',
            'core.mail_template',
            'core.activity_log',      // NEW: Activity log viewing
            'core.login_log',         // NEW: Login log viewing

            // Material Taxonomy
            'core.material_type',
            'core.material_category',
            'core.material_subcategory',

            // Items & Parties
            'core.item',
            'core.party',

            // CRM masters
            'crm.lead_source',
            'crm.lead_stage',

            // CRM main entities
            'crm.lead',
            'crm.quotation',

            // Project & BOM
            'project.project',
            'project.bom',

            // BOM Templates
            'project.bom_template',

            // Purchase
            'purchase.indent',
            'purchase.rfq',
            'purchase.po',
            'purchase.bill',

            // Store
            'store.stock',
            'store.material_receipt',
            'store.requisition',
            'store.issue',
            'store.return',

            // Accounting
            'accounting.accounts',
            'accounting.vouchers',

            // Standard terms
            'standard-terms',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        $allPermissions = [];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                $name = "{$entity}.{$action}";

                $perm = Permission::firstOrCreate(
                    [
                        'name'       => $name,
                        'guard_name' => 'web',
                    ]
                );

                $allPermissions[] = $perm->name;
            }
        }

        // Additional special permissions
        $specialPermissions = [
            // CRM
            'crm.quotation.accept',
            
            // BOM
            'project.bom.finalize',
            
            // Access control
            'core.access.manage',
            
            // Purchase
            'purchase.indent.approve',
            'purchase.rfq.send',
            'purchase.po.approve',
            'purchase.po.send',
            
            // Store
            'store.material_receipt.post',
            'store.issue.post_to_accounts',
            
            // User management
            'core.user.reset_password',    // NEW: Reset other user's password
            'core.user.toggle_status',     // NEW: Activate/deactivate users
            
            // Logs (view only, no create/update/delete needed)
            'core.activity_log.export',    // NEW: Export activity logs
            'core.login_log.export',       // NEW: Export login logs
            'core.login_log.unlock',       // NEW: Unlock locked accounts
        ];

        foreach ($specialPermissions as $permName) {
            $perm = Permission::firstOrCreate([
                'name'       => $permName,
                'guard_name' => 'web',
            ]);
            $allPermissions[] = $perm->name;
        }

        // Roles
        $super = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $super->syncPermissions($allPermissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        // Admin gets all except some sensitive permissions
        $adminPermissions = array_filter($allPermissions, function ($perm) {
            return !in_array($perm, [
                'core.access.manage', // Only super-admin can manage access
            ]);
        });
        $admin->syncPermissions($adminPermissions);

        // Viewer: only *.view permissions
        $viewerPerms = array_filter($allPermissions, function (string $perm) {
            return str_ends_with($perm, '.view');
        });

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions($viewerPerms);

        // Manager role (new)
        $managerPerms = array_filter($allPermissions, function (string $perm) {
            // Managers can view everything, create/update most things, but limited delete
            if (str_ends_with($perm, '.view')) return true;
            if (str_ends_with($perm, '.create')) return true;
            if (str_ends_with($perm, '.update')) return true;
            if (str_contains($perm, '.approve')) return true;
            // No delete permissions for managers
            return false;
        });

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions($managerPerms);

        // Operator role (new) - basic data entry
        $operatorPerms = array_filter($allPermissions, function (string $perm) {
            $allowedModules = ['crm.lead', 'crm.quotation', 'store.requisition', 'store.issue', 'store.return'];
            
            foreach ($allowedModules as $module) {
                if (str_starts_with($perm, $module)) {
                    return str_ends_with($perm, '.view') || 
                           str_ends_with($perm, '.create') || 
                           str_ends_with($perm, '.update');
                }
            }
            
            // View-only for other modules
            if (str_ends_with($perm, '.view')) {
                return str_starts_with($perm, 'core.') || 
                       str_starts_with($perm, 'project.') ||
                       str_starts_with($perm, 'store.stock');
            }
            
            return false;
        });

        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator->syncPermissions($operatorPerms);

        // Clear cache again just to be safe
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
