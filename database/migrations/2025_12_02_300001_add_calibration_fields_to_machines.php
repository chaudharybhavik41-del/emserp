<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->boolean('requires_calibration')->default(false)->after('maintenance_alert_days');
            $table->integer('calibration_frequency_months')->nullable()->after('requires_calibration');
            $table->date('last_calibration_date')->nullable()->after('calibration_frequency_months');
            $table->date('next_calibration_due_date')->nullable()->after('last_calibration_date');
            $table->integer('calibration_alert_days')->default(15)->after('next_calibration_due_date');
            $table->string('calibration_agency', 200)->nullable()->after('calibration_alert_days');
            
            // Indexes
            $table->index('requires_calibration');
            $table->index('next_calibration_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropIndex(['requires_calibration']);
            $table->dropIndex(['next_calibration_due_date']);
            
            $table->dropColumn([
                'requires_calibration',
                'calibration_frequency_months',
                'last_calibration_date',
                'next_calibration_due_date',
                'calibration_alert_days',
                'calibration_agency',
            ]);
        });
    }
};
