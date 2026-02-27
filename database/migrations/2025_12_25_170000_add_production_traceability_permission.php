<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spatie Permission tables
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permName = 'production.traceability.view';
        $guard = 'web';

        $permId = DB::table('permissions')
            ->where('name', $permName)
            ->where('guard_name', $guard)
            ->value('id');

        if (!$permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => $permName,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant to super-admin if present
        $roleId = DB::table('roles')->where('name', 'super-admin')->where('guard_name', $guard)->value('id');
        if ($roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep permission row to avoid breaking RBAC expectations; no destructive rollback.
    }
};
