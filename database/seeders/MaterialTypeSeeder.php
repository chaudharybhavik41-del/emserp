<?php

namespace Database\Seeders;

use App\Models\MaterialType;
use Illuminate\Database\Seeder;

class MaterialTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'RAW',        'name' => 'Raw Material'],
            ['code' => 'CONSUMABLE', 'name' => 'Consumable'],
            ['code' => 'FINISHED',   'name' => 'Finished Goods'],
            ['code' => 'SERVICE',    'name' => 'Service'],
        ];

        $sort = 1;
        foreach ($types as $data) {
            MaterialType::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'        => $data['name'],
                    'sort_order'  => $sort++,
                    'is_active'   => true,
                    'description' => null,
                ]
            );
        }
    }
}
