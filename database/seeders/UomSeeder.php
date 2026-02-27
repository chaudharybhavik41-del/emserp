<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'M',   'name' => 'Meter',        'category' => 'length', 'decimal_places' => 3],
            ['code' => 'MM',  'name' => 'Millimetre',   'category' => 'length', 'decimal_places' => 1],
            ['code' => 'KG',  'name' => 'Kilogram',     'category' => 'weight', 'decimal_places' => 3],
            ['code' => 'TON', 'name' => 'Metric Ton',   'category' => 'weight', 'decimal_places' => 3],
            ['code' => 'PCS', 'name' => 'Piece',        'category' => 'count',  'decimal_places' => 0],
            ['code' => 'LTR', 'name' => 'Litre',        'category' => 'volume', 'decimal_places' => 3],
        ];

        foreach ($rows as $row) {
            Uom::firstOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
