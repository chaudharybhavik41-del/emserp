<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('material_types')
            ->where('code', 'SPARE')
            ->exists();

        if ($exists) {
            return;
        }

        $nextSortOrder = (int) DB::table('material_types')->max('sort_order');

        DB::table('material_types')->insert([
            'code' => 'SPARE',
            'name' => 'Spare Parts',
            'description' => 'Machine and equipment spare parts',
            'accounting_usage' => 'inventory',
            'sort_order' => $nextSortOrder > 0 ? $nextSortOrder + 1 : 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('material_types')
            ->where('code', 'SPARE')
            ->delete();
    }
};
