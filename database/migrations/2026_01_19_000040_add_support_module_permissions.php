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

        $now = now();

        $permissions = [
            // Support: Standard Document Library
            'support.document.view',
            'support.document.create',
            'support.document.update',
            'support.document.delete',

            // Support: Daily Digest / Newsletter
            'support.digest.view',
            'support.digest.update',
            'support.digest.send',
        ];

        foreach ($permissions as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'name'       => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Assign these permissions to super-admin and admin (do NOT revoke anything).
        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('roles')
                ->whereIn('name', ['super-admin', 'admin'])
                ->pluck('id', 'name');

            if ($roleIds->isNotEmpty()) {
                $permIds = DB::table('permissions')
                    ->whereIn('name', $permissions)
                    ->where('guard_name', 'web')
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permIds as $permId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $permId,
                                'role_id'       => $roleId,
                            ],
                            []
                        );
                    }
                }
            }
        }

        // Clear Spatie permission cache if available.
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // We intentionally do not remove permissions in down() to avoid breaking running systems.
    }
};
