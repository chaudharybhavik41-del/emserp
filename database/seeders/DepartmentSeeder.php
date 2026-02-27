<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'PUR',  'name' => 'Purchase'],
            ['code' => 'STR',  'name' => 'Stores'],
            ['code' => 'ACC',  'name' => 'Accounts'],
            ['code' => 'FAB',  'name' => 'Fabrication'],
        ];

        foreach ($rows as $row) {
            Department::firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'is_active' => true]
            );
        }
    }
}
