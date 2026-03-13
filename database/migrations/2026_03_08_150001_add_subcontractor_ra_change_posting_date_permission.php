<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permission = 'subcontractor_ra.change_posting_date';
        $now = now();

        $exists = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('roles')
                ->whereIn('name', ['super-admin', 'admin', 'Admin', 'manager'])
                ->pluck('id');

            $permissionId = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId) {
                foreach ($roleIds as $roleId) {
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
        // Keep permission row to avoid breaking existing RBAC assignments.
    }
};
