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
            $table->decimal('ot_rate_multiplier', 4, 2)
                ->default(1.50)
                ->after('ot_allowed');
        });

        DB::table('hr_attendance_policies')
            ->whereNull('ot_rate_multiplier')
            ->update(['ot_rate_multiplier' => 1.50]);
    }

    public function down(): void
    {
        Schema::table('hr_attendance_policies', function (Blueprint $table) {
            $table->dropColumn('ot_rate_multiplier');
        });
    }
};
