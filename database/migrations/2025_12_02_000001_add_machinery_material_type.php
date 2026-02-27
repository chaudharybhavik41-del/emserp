<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add MACHINERY as a new material type with fixed_asset accounting usage
     * This allows machinery to use existing material taxonomy infrastructure
     */
    public function up(): void
    {
        // Check if MACHINERY type already exists
        $exists = DB::table('material_types')
            ->where('code', 'MACHINERY')
            ->exists();

        if (!$exists) {
            DB::table('material_types')->insert([
                'code' => 'MACHINERY',
                'name' => 'Machinery & Equipment',
                'description' => 'Fabrication machines, equipment and tools',
                'accounting_usage' => 'fixed_asset', // Different from inventory
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('material_types')
            ->where('code', 'MACHINERY')
            ->delete();
    }
};
