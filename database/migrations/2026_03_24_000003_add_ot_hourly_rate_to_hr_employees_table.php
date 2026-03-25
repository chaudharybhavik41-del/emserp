<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->decimal('ot_hourly_rate', 10, 2)->nullable()->after('overtime_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn('ot_hourly_rate');
        });
    }
};
