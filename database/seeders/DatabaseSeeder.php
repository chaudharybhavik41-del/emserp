<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Remove the Test User creation – you can create users via /register or tinker.
        // If you ever want a test user again, we’ll add a dedicated seeder.

        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            UomSeeder::class,
            MailProfileSeeder::class,
            MailTemplateSeeder::class,
          	MaterialTypeSeeder::class,
          	AccountingMasterSeeder::class,
           HrRolesAndPermissionsSeeder::class,
        	  TaskPermissionsSeeder::class,
           TaskManagementSeeder::class,
          ProductionPermissionSeeder::class,
          ProductionActivitySeeder::class,
        ]);
    }
}
