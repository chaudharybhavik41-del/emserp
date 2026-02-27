<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HrRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /**
         * Base CRUD permissions per HR module
         * (mapped from your controllers)
         */
        $entitiesWithActions = [
            // Dashboard: only view
            'hr.dashboard'  => ['view'],

            // Employees module
            'hr.employee'   => ['view', 'create', 'update', 'delete'],

            // Attendance module
            'hr.attendance' => ['view', 'create', 'update', 'delete'],

            // Leave module
            'hr.leave'      => ['view', 'create', 'update', 'delete'],

            // Payroll module
            'hr.payroll'    => ['view', 'create', 'update', 'delete'],
        ];

        $allPermissions = [];

        foreach ($entitiesWithActions as $entity => $actions) {
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

        /**
         * Extra permissions derived from controller methods
         *
         * HrEmployeeController:
         *   confirm, separation, idCard
         *
         * HrLeaveController:
         *   approve, reject, cancel, bulkApprove, yearEndProcessing, getEmployeeBalance, calendar, balanceReport
         *
         * HrAttendanceController:
         *   process, approveOt, rejectOt, bulkOtApproval, regularization, approveRegularization
         *
         * HrPayrollController:
         *   process, approve, pay, bulk operations, hold, release, bankStatement, pfReport, esiReport
         */
        $specialPermissions = [
            // Employee extras
            'hr.employee.confirm',
            'hr.employee.separation',
            'hr.employee.id_card',

            // Leave extras
            'hr.leave.approve',
            'hr.leave.reject',
            'hr.leave.cancel',
            'hr.leave.bulk_approve',
            'hr.leave.year_end_processing',
            'hr.leave.balance_report',
            'hr.leave.calendar',
            'hr.leave.get_employee_balance',

            // Attendance extras
            'hr.attendance.process',
            'hr.attendance.approve_ot',
            'hr.attendance.reject_ot',
            'hr.attendance.bulk_ot_approval',
            'hr.attendance.regularization',
            'hr.attendance.approve_regularization',

            // Payroll extras
            'hr.payroll.process',
            'hr.payroll.approve',
            'hr.payroll.pay',
            'hr.payroll.bank_statement',
            'hr.payroll.pf_report',
            'hr.payroll.esi_report',
            'hr.payroll.hold',
            'hr.payroll.release',
        ];

        foreach ($specialPermissions as $permName) {
            $perm = Permission::firstOrCreate([
                'name'       => $permName,
                'guard_name' => 'web',
            ]);

            $allPermissions[] = $perm->name;
        }

        /**
         * Attach these HR permissions to existing roles
         * without disturbing what your main RolesAndPermissionsSeeder already did.
         */

        // Super Admin: gets everything
        $super = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $super->givePermissionTo($allPermissions);

        // Admin: also gets everything here (your core seeder already limits sensitive perms)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($allPermissions);

        // Viewer: only *.view
        $viewerPerms = array_filter($allPermissions, function (string $perm) {
            return str_ends_with($perm, '.view');
        });

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo($viewerPerms);

        // Manager: view/create/update + any permission containing ".approve"
        $managerPerms = array_filter($allPermissions, function (string $perm) {
            if (str_ends_with($perm, '.view')) return true;
            if (str_ends_with($perm, '.create')) return true;
            if (str_ends_with($perm, '.update')) return true;
            if (str_contains($perm, '.approve')) return true;
            // No delete / destructive perms
            return false;
        });

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo($managerPerms);

        // Operator: basic data entry for attendance + leave, view-only for everything else
        $operatorPerms = array_filter($allPermissions, function (string $perm) {
            $allowedModules = ['hr.attendance', 'hr.leave'];

            foreach ($allowedModules as $module) {
                if (str_starts_with($perm, $module)) {
                    return str_ends_with($perm, '.view')
                        || str_ends_with($perm, '.create')
                        || str_ends_with($perm, '.update');
                }
            }

            // For other HR modules (employee, payroll, dashboard) => view-only
            return str_ends_with($perm, '.view');
        });

        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator->givePermissionTo($operatorPerms);

        // Clear cache again just to be safe
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
