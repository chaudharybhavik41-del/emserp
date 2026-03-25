<?php

namespace Database\Seeders;

use App\Models\MaterialType;
use Illuminate\Database\Seeder;

class MaterialTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'RAW',        'name' => 'Raw Material', 'accounting_usage' => 'inventory'],
            ['code' => 'CONSUMABLE', 'name' => 'Consumable', 'accounting_usage' => 'inventory'],
            ['code' => 'SPARE',      'name' => 'Spare Parts', 'accounting_usage' => 'inventory'],
            ['code' => 'FINISHED',   'name' => 'Finished Goods', 'accounting_usage' => 'inventory'],
            ['code' => 'SERVICE',    'name' => 'Service', 'accounting_usage' => 'expense'],
        ];

        $sort = 1;
        foreach ($types as $data) {
            MaterialType::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'        => $data['name'],
                    'accounting_usage' => $data['accounting_usage'],
                    'sort_order'  => $sort++,
                    'is_active'   => true,
                    'description' => null,
                ]
            );
        }
    }
}
