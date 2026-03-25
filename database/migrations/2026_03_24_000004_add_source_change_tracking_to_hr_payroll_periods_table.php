<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->timestamp('source_data_changed_at')->nullable()->after('paid_by');
            $table->string('source_data_change_reason', 255)->nullable()->after('source_data_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['source_data_changed_at', 'source_data_change_reason']);
        });
    }
};
