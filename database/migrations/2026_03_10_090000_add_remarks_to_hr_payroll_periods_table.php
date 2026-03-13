<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_payroll_periods') || Schema::hasColumn('hr_payroll_periods', 'remarks')) {
            return;
        }

        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_payroll_periods') || ! Schema::hasColumn('hr_payroll_periods', 'remarks')) {
            return;
        }

        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
