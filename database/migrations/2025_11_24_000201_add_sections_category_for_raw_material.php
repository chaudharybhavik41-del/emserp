
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find RAW material type
        $rawTypeId = DB::table('material_types')
            ->where('code', 'RAW')
            ->value('id');

        if (! $rawTypeId) {
            return;
        }

        $exists = DB::table('material_categories')
            ->where('material_type_id', $rawTypeId)
            ->where('code', 'SEC')
            ->exists();

        if (! $exists) {
            DB::table('material_categories')->insert([
                'material_type_id' => $rawTypeId,
                'code'             => 'SEC',
                'name'             => 'Sections',
                'description'      => 'Rolled sections (ISMB, ISMC, ISA, etc.)',
                'sort_order'       => 10,
                'is_active'        => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        $rawTypeId = DB::table('material_types')
            ->where('code', 'RAW')
            ->value('id');

        if (! $rawTypeId) {
            return;
        }

        DB::table('material_categories')
            ->where('material_type_id', $rawTypeId)
            ->where('code', 'SEC')
            ->delete();
    }
};
