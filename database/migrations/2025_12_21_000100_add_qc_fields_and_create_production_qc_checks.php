<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add QC tracking fields to plan item activities
        if (Schema::hasTable('production_plan_item_activities')) {
            Schema::table('production_plan_item_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('production_plan_item_activities', 'qc_status')) {
                    $table->enum('qc_status', ['na', 'pending', 'passed', 'failed'])->default('na')->after('status');
                    $table->foreignId('qc_by')->nullable()->after('qc_status')->constrained('users')->nullOnDelete();
                    $table->timestamp('qc_at')->nullable()->after('qc_by');
                    $table->string('qc_remarks', 500)->nullable()->after('qc_at');
                }
            });
        }

        // QC checks log table (audit trail for QC gate)
        if (!Schema::hasTable('production_qc_checks')) {
            Schema::create('production_qc_checks', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('production_plan_id')->constrained('production_plans')->restrictOnDelete();
                $table->foreignId('production_activity_id')->constrained('production_activities')->restrictOnDelete();

                $table->foreignId('production_plan_item_id')->nullable()->constrained('production_plan_items')->nullOnDelete();
                $table->foreignId('production_plan_item_activity_id')->nullable()->constrained('production_plan_item_activities')->nullOnDelete();

                $table->foreignId('production_dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->foreignId('production_dpr_line_id')->nullable()->constrained('production_dpr_lines')->nullOnDelete();

                $table->enum('result', ['pending', 'passed', 'failed'])->default('pending');
                $table->text('remarks')->nullable();

                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('checked_at')->nullable();

                $table->timestamps();

                $table->index(['project_id', 'result'], 'idx_prod_qc_project_result');
                $table->index(['production_plan_id', 'production_activity_id'], 'idx_prod_qc_plan_activity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_qc_checks');

        if (Schema::hasTable('production_plan_item_activities')) {
            Schema::table('production_plan_item_activities', function (Blueprint $table) {
                if (Schema::hasColumn('production_plan_item_activities', 'qc_status')) {
                    $table->dropConstrainedForeignId('qc_by');
                    $table->dropColumn(['qc_status', 'qc_at', 'qc_remarks']);
                }
            });
        }
    }
};
