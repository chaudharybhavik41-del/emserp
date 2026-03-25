<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_policies', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('description');
            $table->string('ot_calculation_basis', 20)->default('basic')->after('ot_rate_multiplier');
        });

        DB::table('hr_attendance_policies')
            ->whereNull('effective_from')
            ->update([
                'effective_from' => DB::raw('DATE(created_at)'),
                'ot_calculation_basis' => DB::raw("COALESCE(ot_calculation_basis, 'basic')"),
            ]);
    }

    public function down(): void
    {
        Schema::table('hr_attendance_policies', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'ot_calculation_basis']);
        });
    }
};
