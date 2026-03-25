<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'hr.candidate.view',
            'hr.candidate.create',
            'hr.candidate.update',
            'hr.candidate.delete',
        ];

        foreach ($permissions as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('roles')
                ->whereIn('name', ['super-admin', 'admin', 'viewer', 'manager', 'operator'])
                ->pluck('id', 'name');

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissions)
                ->where('guard_name', 'web')
                ->pluck('id', 'name');

            $rolePermissions = [
                'super-admin' => $permissions,
                'admin' => $permissions,
                'viewer' => ['hr.candidate.view'],
                'manager' => ['hr.candidate.view', 'hr.candidate.create', 'hr.candidate.update'],
                'operator' => ['hr.candidate.view'],
            ];

            foreach ($rolePermissions as $roleName => $permissionNames) {
                $roleId = $roleIds[$roleName] ?? null;

                if (! $roleId) {
                    continue;
                }

                foreach ($permissionNames as $permissionName) {
                    $permissionId = $permissionIds[$permissionName] ?? null;

                    if (! $permissionId) {
                        continue;
                    }

                    DB::table('role_has_permissions')->updateOrInsert(
                        [
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ],
                        []
                    );
                }
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Intentionally left blank to avoid removing permissions from live systems.
    }
};
