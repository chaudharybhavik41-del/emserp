<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add machinery categories under MACHINERY material type
     * Using existing material_categories table structure
     */
    public function up(): void
    {
        $machineryTypeId = DB::table('material_types')
            ->where('code', 'MACHINERY')
            ->value('id');

        if (!$machineryTypeId) {
            return; // MACHINERY type must exist first
        }

        $categories = [
            ['code' => 'CUTTING',  'name' => 'Cutting Machines',       'description' => 'Plasma, Gas, CNC Cutting', 'sort_order' => 10],
            ['code' => 'DRILLING', 'name' => 'Drilling Machines',      'description' => 'Pillar Drill, CNC Drilling', 'sort_order' => 20],
            ['code' => 'WELDING',  'name' => 'Welding Equipment',      'description' => 'SAW, Manual Welding, MIG/TIG', 'sort_order' => 30],
            ['code' => 'CRANE',    'name' => 'Material Handling',      'description' => 'EOT Crane, Forklift, Hoist', 'sort_order' => 40],
            ['code' => 'TESTING',  'name' => 'Testing Equipment',      'description' => 'UT, MPT, Dimensional Testing', 'sort_order' => 50],
            ['code' => 'GRINDING', 'name' => 'Grinding & Finishing',   'description' => 'Angle Grinder, Belt Grinder', 'sort_order' => 60],
            ['code' => 'OTHER',    'name' => 'Other Equipment',        'description' => 'Miscellaneous machinery', 'sort_order' => 99],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('material_categories')
                ->where('material_type_id', $machineryTypeId)
                ->where('code', $cat['code'])
                ->exists();

            if (!$exists) {
                DB::table('material_categories')->insert([
                    'material_type_id' => $machineryTypeId,
                    'code' => $cat['code'],
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $machineryTypeId = DB::table('material_types')
            ->where('code', 'MACHINERY')
            ->value('id');

        if ($machineryTypeId) {
            DB::table('material_categories')
                ->where('material_type_id', $machineryTypeId)
                ->delete();
        }
    }
};
