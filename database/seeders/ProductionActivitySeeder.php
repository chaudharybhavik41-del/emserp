<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Production\ProductionActivity;
use App\Models\Uom;

class ProductionActivitySeeder extends Seeder
{
    public function run(): void
    {
        $uomKg  = Uom::where('code', 'KG')->first();
        $uomM   = Uom::where('code', 'M')->first();
        $uomSqm = Uom::where('code', 'SQM')->first();
        $uomNos = Uom::where('code', 'NOS')->first();

        $rows = [
            [
                'code' => 'CUT',
                'name' => 'Cutting',
                'applies_to' => 'part',
                'default_sequence' => 10,
                'billing_uom_id' => $uomM?->id,
                'calculation_method' => 'meter_from_len',
                'is_fitupp' => false,
                'requires_machine' => true,
                'requires_qc' => false,
                'is_active' => true,
            ],
            [
                'code' => 'BEVEL',
                'name' => 'Beveling / Edge Prep',
                'applies_to' => 'part',
                'default_sequence' => 20,
                'billing_uom_id' => $uomM?->id,
                'calculation_method' => 'meter_from_len',
                'is_fitupp' => false,
                'requires_machine' => false,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'FITUP',
                'name' => 'Fitup',
                'applies_to' => 'both',
                'default_sequence' => 30,
                'billing_uom_id' => $uomKg?->id,
                'calculation_method' => 'kg_from_weight',
                'is_fitupp' => true,
                'requires_machine' => false,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SAW',
                'name' => 'SAW Welding',
                'applies_to' => 'assembly',
                'default_sequence' => 40,
                'billing_uom_id' => $uomKg?->id,
                'calculation_method' => 'kg_from_weight',
                'is_fitupp' => false,
                'requires_machine' => true,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MANUAL_WELD',
                'name' => 'Manual Welding',
                'applies_to' => 'assembly',
                'default_sequence' => 50,
                'billing_uom_id' => $uomKg?->id,
                'calculation_method' => 'kg_from_weight',
                'is_fitupp' => false,
                'requires_machine' => false,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'DRILL',
                'name' => 'Drilling',
                'applies_to' => 'assembly',
                'default_sequence' => 60,
                'billing_uom_id' => $uomNos?->id,
                'calculation_method' => 'nos',
                'is_fitupp' => false,
                'requires_machine' => true,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'BLAST',
                'name' => 'Blasting',
                'applies_to' => 'assembly',
                'default_sequence' => 70,
                'billing_uom_id' => $uomSqm?->id,
                'calculation_method' => 'sqm_from_area',
                'is_fitupp' => false,
                'requires_machine' => false,
                'requires_qc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'PAINT',
                'name' => 'Painting',
                'applies_to' => 'assembly',
                'default_sequence' => 80,
                'billing_uom_id' => $uomSqm?->id,
                'calculation_method' => 'sqm_from_area',
                'is_fitupp' => false,
                'requires_machine' => false,
                'requires_qc' => true,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            ProductionActivity::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
