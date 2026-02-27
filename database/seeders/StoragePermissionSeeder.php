<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StoragePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions so Spatie sees new ones immediately
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Storage module permissions
        $permissions = [
            'storage.admin',
          
          	// Menu / module access
            'storage.view',

            // Folder permissions
            'storage.folder.view',
            'storage.folder.create',
            'storage.folder.update',
            'storage.folder.delete',

            // File permissions
            'storage.file.view',
            'storage.file.upload',
            'storage.file.download',
            'storage.file.update',
            'storage.file.delete',

            // Optional (if you build a screen to manage who has storage access)
            'storage.access.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Super Admin role: gets everything for Storage
        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        // Additive (won’t remove existing permissions)
        $superAdmin->givePermissionTo($permissions);

        // Clear cache again just to be safe
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Storage permissions seeded and granted to super-admin.');
    }
}
